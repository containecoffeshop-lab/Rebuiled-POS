<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseRecommendation
{
    private const LOOKBACK_DAYS = 30;
    private const TARGET_COVER_DAYS = 7;

    public function forMaterials(Collection $materials): Collection
    {
        if ($materials->isEmpty()) {
            return collect();
        }

        $materialIds = $materials->modelKeys();
        $usage = DB::table('stock_movements')
            ->join('material_stock_batches', 'material_stock_batches.id', '=', 'stock_movements.material_stock_batch_id')
            ->join('transactions', 'transactions.id', '=', 'stock_movements.transaction_id')
            ->whereIn('material_stock_batches.material_id', $materialIds)
            ->where('stock_movements.type', 'out')
            ->where(function ($query): void {
                $query->where(function ($sales): void {
                    $sales->where('transactions.transaction_kind', 'sale')
                        ->where('transactions.status', 'paid');
                })->orWhere(function ($consumption): void {
                    $consumption->where('transactions.transaction_kind', 'employee_consumption')
                        ->where('transactions.status', 'consumed');
                });
            })
            ->where('stock_movements.created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->selectRaw('material_stock_batches.material_id, SUM(stock_movements.quantity) AS quantity')
            ->groupBy('material_stock_batches.material_id')
            ->pluck('quantity', 'material_id');
        $incoming = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->whereIn('purchase_items.material_id', $materialIds)
            ->where('purchases.status', 'confirmed')
            ->selectRaw('purchase_items.material_id, SUM(purchase_items.quantity) AS quantity')
            ->groupBy('purchase_items.material_id')
            ->pluck('quantity', 'material_id');

        return $materials->map(function (Material $material) use ($usage, $incoming): array {
            $recentUsage = (float) ($usage->get($material->id) ?? 0);
            $incomingQuantity = (float) ($incoming->get($material->id) ?? 0);
            $target = max(
                (float) $material->minimum_quantity,
                $recentUsage / self::LOOKBACK_DAYS * self::TARGET_COVER_DAYS,
            );
            $suggested = max(0, $target - (float) $material->stock_on_hand - $incomingQuantity);

            return [
                'material' => $material,
                'recent_usage' => $recentUsage,
                'incoming_quantity' => $incomingQuantity,
                'suggested_quantity' => ceil($suggested * 1000) / 1000,
            ];
        })
            ->filter(fn (array $row): bool => $row['suggested_quantity'] > 0)
            ->sortByDesc('suggested_quantity')
            ->values();
    }
}
