<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductInventory
{
    public function onHandByVariant(array $variantIds): Collection
    {
        return $this->activeBatchBalances($variantIds)
            ->groupBy('product_variant_id')
            ->map(fn (Collection $batches): float => $batches->sum('on_hand_milli') / 1000);
    }

    public function physicalOnHandByVariant(array $variantIds): Collection
    {
        return $this->batchBalances($variantIds)
            ->groupBy('product_variant_id')
            ->map(fn (Collection $batches): float => $batches->sum('on_hand_milli') / 1000);
    }

    public function receive(
        int $variantId,
        string $batchCode,
        int $quantityMilli,
        ?string $expiresAt,
        ?int $unitCost,
        string $description = 'Stok masuk',
    ): void {
        if ($quantityMilli < 1) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah stok masuk harus lebih dari nol.']);
        }

        DB::transaction(function () use ($variantId, $batchCode, $quantityMilli, $expiresAt, $unitCost, $description): void {
            ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
            $batch = ProductStockBatch::query()->create([
                'product_variant_id' => $variantId,
                'batch_code' => $batchCode,
                'expires_at' => $expiresAt,
                'unit_cost' => $unitCost,
            ]);
            $batch->movements()->create([
                'type' => 'in',
                'quantity' => $this->fromMilli($quantityMilli),
                'unit_cost' => $unitCost,
                'cost_amount' => $this->costForQuantity($unitCost, $quantityMilli),
                'description' => $description,
            ]);
        }, attempts: 3);
    }

    public function removeFromBatch(int $batchId, int $quantityMilli, string $description): void
    {
        if ($quantityMilli < 1) {
            throw ValidationException::withMessages(['items' => 'Jumlah stok yang dikembalikan tidak valid.']);
        }

        DB::transaction(function () use ($batchId, $quantityMilli, $description): void {
            $batch = ProductStockBatch::query()->findOrFail($batchId);
            ProductVariant::query()->whereKey($batch->product_variant_id)->lockForUpdate()->firstOrFail();
            $batch = ProductStockBatch::query()->whereKey($batchId)->lockForUpdate()->firstOrFail();
            $onHand = DB::table('product_stock_movements')
                ->where('product_stock_batch_id', $batch->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) AS on_hand")
                ->value('on_hand');
            $availableMilli = $this->toMilli($onHand);

            if ($availableMilli < $quantityMilli) {
                throw ValidationException::withMessages([
                    'items' => 'Stok pada batch '.$batch->batch_code.' tidak mencukupi untuk retur. Sisa batch: '
                        .number_format($availableMilli / 1000, 3, ',', '.').'.',
                ]);
            }

            $batch->movements()->create([
                'type' => 'out',
                'quantity' => $this->fromMilli($quantityMilli),
                'unit_cost' => $batch->unit_cost,
                'cost_amount' => $this->costForQuantity($batch->unit_cost, $quantityMilli),
                'description' => $description,
            ]);
        }, attempts: 3);
    }

    public function assertSellable(array $items): void
    {
        $requirements = $this->requirements($items);
        $ids = $requirements->keys()->sort()->values()->all();
        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $products = Product::query()->whereIn('id', $variants->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        if ($variants->count() !== count($ids)
            || $variants->contains(fn (ProductVariant $variant) => ! $variant->is_available || ! ($products->get($variant->product_id)?->is_available ?? false))) {
            throw ValidationException::withMessages(['items' => 'Ada produk yang baru saja habis atau tidak tersedia. Segarkan halaman lalu coba kembali.']);
        }

        $this->assertStock($requirements, lock: true);
    }

    public function assertStockForOnlineOrder(array $items): void
    {
        $this->assertSellable($items);
    }

    public function consume(Transaction $transaction): array
    {
        $details = $transaction->details()
            ->whereNotNull('product_variant_id')
            ->orderBy('product_variant_id')
            ->orderBy('id')
            ->get();

        if ($details->isEmpty()) {
            return ['total' => 0, 'complete' => true];
        }

        $requirements = $details
            ->groupBy('product_variant_id')
            ->map(fn (Collection $rows): int => $rows->sum(fn ($row): int => $row->quantity * 1000));
        $batches = $this->assertStock($requirements, lock: true);
        $totalCost = 0;
        $complete = true;

        foreach ($requirements as $variantId => $requiredMilli) {
            $remaining = $requiredMilli;

            foreach ($batches->get($variantId, collect()) as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $quantityMilli = min($remaining, $batch->on_hand_milli);
                $unitCost = $batch->record->unit_cost;
                $costAmount = $this->costForQuantity($unitCost, $quantityMilli);
                $batch->record->movements()->create([
                    'transaction_id' => $transaction->id,
                    'type' => 'out',
                    'quantity' => $this->fromMilli($quantityMilli),
                    'unit_cost' => $unitCost,
                    'cost_amount' => $costAmount,
                    'description' => 'Transaksi '.$transaction->invoice,
                ]);

                if ($costAmount === null) {
                    $complete = false;
                } else {
                    $totalCost += $costAmount;
                }

                $remaining -= $quantityMilli;
            }
        }

        return ['total' => $totalCost, 'complete' => $complete];
    }

    public function reconcilePhysicalCount(int $variantId, int $countedMilli, string $reference): int
    {
        if ($countedMilli < 0) {
            throw ValidationException::withMessages(['counted_quantity' => 'Jumlah hasil stok opname tidak valid.']);
        }

        return DB::transaction(function () use ($variantId, $countedMilli, $reference): int {
            ProductVariant::query()->whereKey($variantId)->lockForUpdate()->firstOrFail();
            $batches = $this->batchBalances([$variantId], lock: true);
            $systemMilli = $batches->sum('on_hand_milli');
            $differenceMilli = $countedMilli - $systemMilli;
            $description = 'Penyesuaian stok '.$reference;

            if ($differenceMilli > 0) {
                $batch = ProductStockBatch::query()->create([
                    'product_variant_id' => $variantId,
                    'batch_code' => substr('OPNAME-'.strtoupper($reference), 0, 64),
                ]);
                $batch->movements()->create([
                    'type' => 'in',
                    'quantity' => $this->fromMilli($differenceMilli),
                    'description' => $description,
                ]);
            } elseif ($differenceMilli < 0) {
                $remainingMilli = abs($differenceMilli);

                foreach ($batches as $batch) {
                    if ($remainingMilli <= 0) {
                        break;
                    }

                    $quantityMilli = min($remainingMilli, $batch->on_hand_milli);
                    $batch->record->movements()->create([
                        'type' => 'out',
                        'quantity' => $this->fromMilli($quantityMilli),
                        'unit_cost' => $batch->record->unit_cost,
                        'cost_amount' => $this->costForQuantity($batch->record->unit_cost, $quantityMilli),
                        'description' => $description,
                    ]);
                    $remainingMilli -= $quantityMilli;
                }

                if ($remainingMilli > 0) {
                    throw ValidationException::withMessages(['counted_quantity' => 'Stok berubah saat opname. Segarkan data dan ulangi pencatatan.']);
                }
            }

            return $systemMilli;
        }, attempts: 3);
    }

    private function requirements(array $items): Collection
    {
        return collect($items)
            ->groupBy('product_variant_id')
            ->map(fn (Collection $rows): int => $rows->sum(fn (array $row): int => (int) $row['quantity'] * 1000));
    }

    private function assertStock(Collection $requirements, bool $lock): Collection
    {
        $ids = $requirements->keys()->sort()->values()->all();

        if ($lock && $ids !== []) {
            ProductVariant::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }

        $batches = $this->activeBatchBalances($ids, $lock)->groupBy('product_variant_id');
        $shortages = [];

        foreach ($requirements as $variantId => $requiredMilli) {
            $availableMilli = $batches->get($variantId, collect())->sum('on_hand_milli');

            if ($availableMilli < $requiredMilli) {
                $variant = ProductVariant::query()->with('product', 'unit')->find($variantId);
                $shortages[] = ($variant ? $variant->product->name.' · '.$variant->label : 'Produk #'.$variantId)
                    .' kurang '.number_format(($requiredMilli - $availableMilli) / 1000, 3, ',', '.')
                    .' '.($variant?->unit?->name ?? '');
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages(['items' => 'Stok produk tidak mencukupi: '.implode('; ', $shortages).'.']);
        }

        return $batches;
    }

    private function activeBatchBalances(array $variantIds, bool $lock = false): Collection
    {
        return $this->batchBalances($variantIds, $lock, activeOnly: true);
    }

    private function batchBalances(array $variantIds, bool $lock = false, bool $activeOnly = false): Collection
    {
        if ($variantIds === []) {
            return collect();
        }

        $query = ProductStockBatch::query()
            ->whereIn('product_variant_id', $variantIds)
            ->when($activeOnly, fn ($query) => $query->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today())))
            ->select(['id', 'product_variant_id', 'batch_code', 'expires_at', 'unit_cost']);

        if ($lock) {
            $query->orderBy('product_variant_id')
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate();
        }

        $batches = $query->get();
        $movementBalances = DB::table('product_stock_movements')
            ->whereIn('product_stock_batch_id', $batches->pluck('id'))
            ->select('product_stock_batch_id')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS on_hand")
            ->groupBy('product_stock_batch_id')
            ->pluck('on_hand', 'product_stock_batch_id');

        return $batches->map(fn (ProductStockBatch $batch): object => (object) [
            'id' => $batch->id,
            'product_variant_id' => $batch->product_variant_id,
            'batch_code' => $batch->batch_code,
            'expires_at' => $batch->expires_at,
            'on_hand_milli' => $this->toMilli($movementBalances->get($batch->id, 0)),
            'record' => $batch,
        ])->filter(fn (object $batch): bool => $batch->on_hand_milli > 0)->values();
    }

    private function toMilli(string|int|float $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function fromMilli(int $quantity): string
    {
        return number_format($quantity / 1000, 3, '.', '');
    }

    private function costForQuantity(?int $unitCost, int $quantityMilli): ?int
    {
        return $unitCost === null ? null : (int) round($unitCost * ($quantityMilli / 1000));
    }
}
