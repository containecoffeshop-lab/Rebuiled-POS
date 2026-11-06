<?php

namespace App\Http\Controllers;

use App\Models\MaterialStockBatch;
use App\Models\ProductStockBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Services\ProductInventory;
use App\Services\ProportionalAmountAllocator;
use App\Services\RecipeInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function index(Request $request): View
    {
        $filters = array_merge(
            ['search' => '', 'status' => 'all', 'purchase_id' => null],
            array_filter($request->validate([
                'search' => ['nullable', 'string', 'max:120'],
                'status' => ['nullable', 'in:all,pending,confirmed,cancelled'],
                'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            ]), fn ($value) => $value !== null),
        );

        $eligiblePurchases = Purchase::query()
            ->where('status', 'received')
            ->with(['supplier', 'items.returnDetails.purchaseReturn'])
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where('purchase_code', 'like', $search)
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $search));
            }))
            ->latest('purchase_date')
            ->paginate(10, ['*'], 'purchases_page')
            ->withQueryString();

        $selectedPurchase = filled($filters['purchase_id'])
            ? Purchase::query()
                ->where('status', 'received')
                ->with(['supplier', 'items.returnDetails.purchaseReturn'])
                ->find($filters['purchase_id'])
            : null;

        $returns = PurchaseReturn::query()
            ->with(['purchase.supplier', 'creator', 'details.purchaseItem'])
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where('return_code', 'like', $search)
                    ->orWhereHas('purchase', fn (Builder $purchase) => $purchase
                        ->where('purchase_code', 'like', $search)
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $search)));
            }))
            ->latest('return_date')
            ->latest('id')
            ->paginate(10, ['*'], 'returns_page')
            ->withQueryString();

        return view('purchase-returns.index', compact('eligiblePurchases', 'selectedPurchase', 'returns', 'filters'));
    }

    public function store(Request $request, ProportionalAmountAllocator $allocator): RedirectResponse
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'integer', 'exists:purchases,id'],
            'return_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'settlement_method' => ['required', 'in:refund,replacement,debt_reduction'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.purchase_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.001', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.replacement_expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);
        $selectedItems = collect($data['items'])->filter(fn (array $item) => filled($item['quantity'] ?? null));

        if ($selectedItems->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Pilih setidaknya satu item dan jumlah yang dikembalikan.']);
        }

        foreach ($selectedItems as $index => $item) {
            if (! filled($item['reason'] ?? null)) {
                throw ValidationException::withMessages(["items.$index.reason" => 'Alasan retur wajib diisi untuk item yang dipilih.']);
            }
        }

        if ($data['settlement_method'] === 'replacement') {
            foreach ($selectedItems as $index => $item) {
                if (filled($item['replacement_expires_at'] ?? null)
                    && $item['replacement_expires_at'] < $data['return_date']) {
                    throw ValidationException::withMessages([
                        "items.$index.replacement_expires_at" => 'Tanggal kedaluwarsa pengganti tidak boleh sebelum tanggal retur.',
                    ]);
                }
            }
        }

        $return = DB::transaction(function () use ($data, $request, $selectedItems, $allocator): PurchaseReturn {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($data['purchase_id']);

            if ($purchase->status !== 'received') {
                throw ValidationException::withMessages(['purchase_id' => 'Retur hanya dapat dibuat untuk pembelian yang sudah diterima.']);
            }

            $items = $purchase->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $itemIds = $selectedItems->pluck('purchase_item_id')->map(fn ($id) => (int) $id);

            if ($itemIds->contains(fn (int $id) => ! $items->has($id))) {
                throw ValidationException::withMessages(['items' => 'Item retur tidak sesuai dengan pembelian yang dipilih.']);
            }

            $alreadyReturned = PurchaseReturnDetail::query()
                ->whereIn('purchase_item_id', $itemIds)
                ->whereHas('purchaseReturn', fn (Builder $query) => $query->whereIn('status', ['pending', 'confirmed']))
                ->select('purchase_item_id')
                ->selectRaw('SUM(quantity) AS returned_quantity')
                ->groupBy('purchase_item_id')
                ->pluck('returned_quantity', 'purchase_item_id');

            foreach ($selectedItems as $item) {
                $itemId = (int) $item['purchase_item_id'];
                $purchaseItem = $items->get($itemId);
                $remainingMilli = $this->toMilli($purchaseItem->quantity) - $this->toMilli($alreadyReturned->get($itemId, 0));
                $quantityMilli = $this->toMilli($item['quantity']);

                if ($quantityMilli > $remainingMilli) {
                    throw ValidationException::withMessages([
                        'items' => 'Jumlah retur '.$purchaseItem->material_name.' melebihi sisa yang dapat diretur ('
                            .$this->formatQuantity($remainingMilli).').',
                    ]);
                }
            }

            $netAmounts = $allocator->allocate(
                (int) $purchase->grand_total,
                $items->mapWithKeys(fn (PurchaseItem $item) => [$item->id => (int) $item->line_total])->all(),
            );
            $return = PurchaseReturn::create([
                'return_code' => $this->newReturnCode(),
                'purchase_id' => $purchase->id,
                'created_by' => $request->user()->id,
                'return_date' => $data['return_date'],
                'settlement_method' => $data['settlement_method'],
                'status' => 'pending',
                'amount' => 0,
                'notes' => $data['notes'] ?? null,
            ]);
            $totalAmount = 0;

            foreach ($selectedItems as $item) {
                $itemId = (int) $item['purchase_item_id'];
                $purchaseItem = $items->get($itemId);
                $previousQuantity = $this->toMilli($alreadyReturned->get($itemId, 0));
                $newQuantity = $previousQuantity + $this->toMilli($item['quantity']);
                $lineAmount = $allocator->share($netAmounts[$itemId] ?? 0, $newQuantity, $this->toMilli($purchaseItem->quantity))
                    - $allocator->share($netAmounts[$itemId] ?? 0, $previousQuantity, $this->toMilli($purchaseItem->quantity));
                $detailAmount = $data['settlement_method'] === 'replacement' ? 0 : $lineAmount;
                $totalAmount += $detailAmount;

                $return->details()->create([
                    'purchase_item_id' => $itemId,
                    'quantity' => number_format($this->toMilli($item['quantity']) / 1000, 3, '.', ''),
                    'amount' => $detailAmount,
                    'replacement_expires_at' => $data['settlement_method'] === 'replacement'
                        ? ($item['replacement_expires_at'] ?? null)
                        : null,
                    'reason' => trim($item['reason']),
                ]);
            }

            if ($data['settlement_method'] === 'debt_reduction') {
                $paid = (int) $purchase->payments()->sum('amount');
                $reservedCredit = (int) $purchase->returns()
                    ->where('settlement_method', 'debt_reduction')
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->sum('amount');

                if ($totalAmount > $purchase->grand_total - $paid - $reservedCredit) {
                    throw ValidationException::withMessages([
                        'items' => 'Nilai pengurangan utang melebihi sisa tagihan pemasok setelah pembayaran dan retur aktif.',
                    ]);
                }
            }

            $return->update(['amount' => $totalAmount]);

            return $return;
        }, attempts: 3);

        return redirect()->route('pos.purchase-returns.index', [
            'search' => Purchase::query()->whereKey($data['purchase_id'])->value('purchase_code'),
        ])->with('success', 'Permintaan retur '.$return->return_code.' berhasil dicatat dan menunggu konfirmasi.');
    }

    public function confirm(PurchaseReturn $purchaseReturn, RecipeInventory $inventory, ProductInventory $productInventory): RedirectResponse
    {
        DB::transaction(function () use ($purchaseReturn, $inventory, $productInventory): void {
            $preview = PurchaseReturn::query()->findOrFail($purchaseReturn->id);
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($preview->purchase_id);
            $locked = PurchaseReturn::query()
                ->with(['details.purchaseItem'])
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['return' => 'Hanya retur yang menunggu konfirmasi dapat diproses.']);
            }

            if ($purchase->status !== 'received') {
                throw ValidationException::withMessages(['purchase' => 'Retur hanya dapat dikonfirmasi untuk pembelian yang sudah diterima.']);
            }

            if ($locked->settlement_method === 'debt_reduction') {
                $paid = (int) $purchase->payments()->sum('amount');
                $otherCredits = (int) $purchase->returns()
                    ->where('settlement_method', 'debt_reduction')
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where('id', '!=', $locked->id)
                    ->sum('amount');

                if ($locked->amount > $purchase->grand_total - $paid - $otherCredits) {
                    throw ValidationException::withMessages([
                        'return' => 'Sisa utang berubah dan tidak lagi cukup untuk pengurangan utang retur ini.',
                    ]);
                }
            }

            foreach ($locked->details as $detail) {
                $purchaseItem = $detail->purchaseItem;
                $quantityMilli = $this->toMilli($detail->quantity);
                $description = 'Retur pembelian '.$purchase->purchase_code.' ('.$locked->return_code.')';

                if ($purchaseItem->item_type === 'product') {
                    $sourceBatch = ProductStockBatch::query()
                        ->where('product_variant_id', $purchaseItem->product_variant_id)
                        ->where('batch_code', 'PB-'.$purchase->purchase_code.'-'.$purchaseItem->id)
                        ->firstOrFail();
                    $productInventory->removeFromBatch($sourceBatch->id, $quantityMilli, $description);

                    if ($locked->settlement_method === 'replacement') {
                        $productInventory->receive(
                            $purchaseItem->product_variant_id,
                            'PBRT-'.$locked->return_code.'-'.$detail->id,
                            $quantityMilli,
                            $detail->replacement_expires_at?->toDateString(),
                            $purchaseItem->unit_cost,
                            'Pengganti '.$description,
                        );
                    }
                } else {
                    $sourceBatch = MaterialStockBatch::query()
                        ->where('material_id', $purchaseItem->material_id)
                        ->where('batch_code', 'PB-'.$purchase->purchase_code.'-'.$purchaseItem->id)
                        ->firstOrFail();
                    $inventory->removeFromBatch($sourceBatch->id, $quantityMilli, $description);

                    if ($locked->settlement_method === 'replacement') {
                        $inventory->receive(
                            $purchaseItem->material_id,
                            'PBRT-'.$locked->return_code.'-'.$detail->id,
                            $quantityMilli,
                            $detail->replacement_expires_at?->toDateString(),
                            'Pengganti '.$description,
                            number_format($purchaseItem->line_total / (float) $purchaseItem->quantity, 6, '.', ''),
                        );
                    }
                }
            }

            $locked->update(['status' => 'confirmed']);

            if ($locked->settlement_method === 'debt_reduction') {
                $paid = (int) $purchase->payments()->sum('amount');
                $debtCredits = (int) $purchase->returns()
                    ->where('settlement_method', 'debt_reduction')
                    ->where('status', 'confirmed')
                    ->sum('amount');
                $purchase->update([
                    'payment_status' => $this->paymentStatus($paid + $debtCredits, (int) $purchase->grand_total),
                ]);
            }
        }, attempts: 3);

        return back()->with('success', 'Retur pembelian dikonfirmasi: batch asal dikurangi; metode penyelesaian diterapkan sesuai catatan.');
    }

    public function cancel(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        DB::transaction(function () use ($purchaseReturn): void {
            $locked = PurchaseReturn::query()->lockForUpdate()->findOrFail($purchaseReturn->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['return' => 'Hanya retur yang masih menunggu konfirmasi dapat dibatalkan.']);
            }

            $locked->update(['status' => 'cancelled']);
        }, attempts: 3);

        return back()->with('success', 'Permintaan retur dibatalkan; jumlah barang dan saldo utang yang dicadangkan kembali tersedia.');
    }

    private function paymentStatus(int $paidAndCredited, int $grandTotal): string
    {
        if ($paidAndCredited >= $grandTotal) {
            return 'paid';
        }

        return $paidAndCredited > 0 ? 'partial' : 'unpaid';
    }

    private function toMilli(string|int|float|null $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function formatQuantity(int $quantityMilli): string
    {
        return rtrim(rtrim(number_format($quantityMilli / 1000, 3, ',', '.'), '0'), ',');
    }

    private function newReturnCode(): string
    {
        do {
            $code = 'RBL-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (PurchaseReturn::query()->where('return_code', $code)->exists());

        return $code;
    }
}
