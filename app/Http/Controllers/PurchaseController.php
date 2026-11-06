<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\ProductInventory;
use App\Services\RecipeInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    private const MAX_TOTAL = 999999999999;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,pending,confirmed,received'],
            'payment_status' => ['nullable', 'in:all,unpaid,partial,paid'],
        ]);
        $oldItems = $request->session()->getOldInput('items', []);
        $oldItems = is_array($oldItems)
            ? collect($oldItems)->filter(fn ($item) => is_array($item))->take(30)->values()->all()
            : [];
        $oldItems = $oldItems ?: [['item_ref' => '', 'quantity' => '1', 'unit_cost' => '', 'expires_at' => '']];

        $purchases = Purchase::query()
            ->with(['supplier', 'items', 'payments', 'returns'])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = '%'.$filters['search'].'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('purchase_code', 'like', $search)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $search));
                });
            })
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['payment_status'] ?? 'all') !== 'all', fn ($query) => $query->where('payment_status', $filters['payment_status']))
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('purchases.index', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'materials' => Material::query()->with('unit')->orderBy('name')->get(),
            'productVariants' => ProductVariant::query()->with(['product', 'unit'])->orderBy('product_id')->orderBy('id')->get(),
            'purchases' => $purchases,
            'oldItems' => $oldItems,
            'oldPurchaseType' => old('purchase_type', 'materials'),
            'filters' => array_merge(['search' => '', 'status' => 'all', 'payment_status' => 'all'], $filters),
        ]);
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $this->validateSupplier($request);
        Supplier::create($data);

        return back()->with('success', 'Pemasok berhasil ditambahkan.');
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $this->validateSupplier($request, $supplier);
        $supplier->update($data);

        return back()->with('success', 'Data pemasok berhasil diperbarui.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'discount_type' => ['nullable', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'initial_payment' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'payment_method' => ['nullable', 'in:cash,transfer'],
            'purchase_type' => ['nullable', 'in:materials,products'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.item_ref' => ['nullable', 'string', 'regex:/^(material|product):\d+$/', 'distinct'],
            'items.*.material_id' => ['nullable', 'integer', 'distinct', 'exists:materials,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.unit_cost' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'items.*.expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'items.required' => 'Tambahkan setidaknya satu bahan atau produk ke pembelian.',
        ]);

        $items = [];
        $seenItems = [];

        foreach ($data['items'] as $index => $item) {
            $reference = $item['item_ref'] ?? null;

            if (filled($reference)) {
                if (filled($item['material_id'] ?? null) || filled($item['product_variant_id'] ?? null)) {
                    throw ValidationException::withMessages(["items.$index.item_ref" => 'Pilih item hanya dengan satu referensi.']);
                }

                [$type, $id] = explode(':', $reference, 2);
                $id = (int) $id;
            } elseif (filled($item['material_id'] ?? null) && ! filled($item['product_variant_id'] ?? null)) {
                $type = 'material';
                $id = (int) $item['material_id'];
            } elseif (filled($item['product_variant_id'] ?? null) && ! filled($item['material_id'] ?? null)) {
                $type = 'product';
                $id = (int) $item['product_variant_id'];
            } else {
                throw ValidationException::withMessages(["items.$index.item_ref" => 'Pilih satu bahan atau varian produk untuk setiap baris.']);
            }

            $key = $type.':'.$id;

            if (isset($seenItems[$key])) {
                throw ValidationException::withMessages(["items.$index.item_ref" => 'Item yang sama cukup dicantumkan satu kali dalam pembelian.']);
            }

            $seenItems[$key] = true;
            $items[] = [...$item, 'item_type' => $type, 'item_id' => $id];
        }

        $types = collect($items)->pluck('item_type')->unique();
        $purchaseType = $data['purchase_type'] ?? ($types->first() === 'product' ? 'products' : 'materials');

        if ($types->count() !== 1 || ($purchaseType === 'materials' && $types->first() !== 'material')
            || ($purchaseType === 'products' && $types->first() !== 'product')) {
            throw ValidationException::withMessages([
                'items' => 'Satu dokumen pembelian hanya dapat memuat bahan atau produk eceran, sesuai jenis yang dipilih.',
            ]);
        }
        $data['purchase_type'] = $purchaseType;

        $initialPayment = (int) ($data['initial_payment'] ?? 0);

        if ($initialPayment > 0 && ! filled($data['payment_method'] ?? null)) {
            throw ValidationException::withMessages(['payment_method' => 'Pilih metode untuk pembayaran awal.']);
        }

        return DB::transaction(function () use ($data, $items, $request, $initialPayment): RedirectResponse {
            $supplier = Supplier::query()->lockForUpdate()->find($data['supplier_id']);

            if (! $supplier) {
                throw ValidationException::withMessages(['supplier_id' => 'Pemasok sudah tidak tersedia. Segarkan halaman dan coba kembali.']);
            }

            $materialIds = collect($items)->where('item_type', 'material')->pluck('item_id')->sort()->values();
            $materials = Material::query()
                ->with('unit')
                ->whereIn('id', $materialIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $variantIds = collect($items)->where('item_type', 'product')->pluck('item_id')->sort()->values();
            $variants = ProductVariant::query()
                ->with(['product', 'unit'])
                ->whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($materials->count() !== $materialIds->unique()->count()
                || $variants->count() !== $variantIds->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'Ada bahan yang sudah tidak tersedia. Segarkan halaman dan coba kembali.']);
            }

            $lines = [];
            $subtotal = 0;

            foreach ($items as $item) {
                $inventoryItem = $item['item_type'] === 'material'
                    ? $materials->get($item['item_id'])
                    : $variants->get($item['item_id']);
                $quantityMilli = (int) round((float) $item['quantity'] * 1000);
                $unitCost = (int) $item['unit_cost'];

                if ($unitCost > intdiv(self::MAX_TOTAL * 1000, $quantityMilli)) {
                    throw ValidationException::withMessages(['items' => 'Nilai salah satu baris pembelian melebihi batas transaksi.']);
                }

                $lineTotal = intdiv($unitCost * $quantityMilli + 500, 1000);

                if ($lineTotal < 1 || $subtotal > self::MAX_TOTAL - $lineTotal) {
                    throw ValidationException::withMessages(['items' => 'Total pembelian melebihi batas transaksi.']);
                }

                $subtotal += $lineTotal;
                $lines[] = [
                    'item_type' => $item['item_type'],
                    'inventory_item' => $inventoryItem,
                    'quantity' => number_format($quantityMilli / 1000, 3, '.', ''),
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                    'expires_at' => $item['expires_at'] ?? null,
                ];
            }

            $discountType = $data['discount_type'] ?? null;
            $discountValue = (int) ($data['discount_value'] ?? 0);

            if ($discountType === 'percentage' && $discountValue > 100) {
                throw ValidationException::withMessages(['discount_value' => 'Persentase diskon maksimal 100%.']);
            }

            if ($discountValue > 0 && ! $discountType) {
                throw ValidationException::withMessages(['discount_type' => 'Pilih jenis diskon.']);
            }

            $discount = match ($discountType) {
                'percentage' => (int) floor($subtotal * $discountValue / 100),
                'fixed' => min($subtotal, $discountValue),
                default => 0,
            };
            $grandTotal = $subtotal - $discount;

            if ($initialPayment > $grandTotal) {
                throw ValidationException::withMessages(['initial_payment' => 'Pembayaran awal tidak boleh melebihi total pembelian.']);
            }

            $purchase = Purchase::create([
                'purchase_code' => $this->newPurchaseCode(),
                'purchase_type' => $data['purchase_type'],
                'supplier_id' => $data['supplier_id'],
                'created_by' => $request->user()->id,
                'purchase_date' => $data['purchase_date'],
                'status' => 'pending',
                'payment_status' => $this->paymentStatus($initialPayment, $grandTotal),
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $inventoryItem = $line['inventory_item'];
                $isMaterial = $line['item_type'] === 'material';
                $purchase->items()->create([
                    'item_type' => $line['item_type'],
                    'material_id' => $isMaterial ? $inventoryItem->id : null,
                    'product_variant_id' => $isMaterial ? null : $inventoryItem->id,
                    'material_name' => $isMaterial
                        ? $inventoryItem->name
                        : $inventoryItem->product->name.' · '.$inventoryItem->label,
                    'unit_name' => $inventoryItem->unit->name,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                    'expires_at' => $line['expires_at'],
                ]);
            }

            if ($initialPayment > 0) {
                $purchase->payments()->create([
                    'paid_at' => today(),
                    'amount' => $initialPayment,
                    'payment_method' => $data['payment_method'],
                ]);
            }

            return back()->with('success', 'Pembelian '.$purchase->purchase_code.' berhasil dibuat.');
        }, attempts: 3);
    }

    public function confirm(Purchase $purchase): RedirectResponse
    {
        DB::transaction(function () use ($purchase): void {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['purchase' => 'Hanya pembelian yang menunggu konfirmasi dapat dikonfirmasi.']);
            }

            $locked->update(['status' => 'confirmed']);
        });

        return back()->with('success', 'Pembelian dikonfirmasi; stok belum berubah sampai barang diterima.');
    }

    public function receive(Purchase $purchase, RecipeInventory $inventory, ProductInventory $productInventory): RedirectResponse
    {
        DB::transaction(function () use ($purchase, $inventory, $productInventory): void {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($locked->status !== 'confirmed') {
                throw ValidationException::withMessages(['purchase' => 'Konfirmasi pembelian sebelum mencatat penerimaan barang.']);
            }

            $items = $locked->items()->orderBy('id')->lockForUpdate()->get();

            foreach ($items as $item) {
                $batchCode = 'PB-'.$locked->purchase_code.'-'.$item->id;
                $quantityMilli = (int) round((float) $item->quantity * 1000);
                $description = 'Penerimaan pembelian '.$locked->purchase_code;

                if ($item->item_type === 'product') {
                    $productInventory->receive($item->product_variant_id, $batchCode, $quantityMilli, $item->expires_at?->toDateString(), $item->unit_cost, $description);
                } else {
                    $inventory->receive(
                        $item->material_id,
                        $batchCode,
                        $quantityMilli,
                        $item->expires_at?->toDateString(),
                        $description,
                        number_format($item->line_total / (float) $item->quantity, 6, '.', ''),
                    );
                }
            }

            $locked->update(['status' => 'received']);
        }, attempts: 3);

        return back()->with('success', 'Barang diterima dan stok sesuai jenis pembelian diperbarui.');
    }

    public function storePayment(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate([
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payment_method' => ['required', 'in:cash,transfer'],
        ]);

        DB::transaction(function () use ($purchase, $data): void {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $paid = (int) $locked->payments()->sum('amount');
            $debtCredits = (int) $locked->returns()
                ->where('settlement_method', 'debt_reduction')
                ->whereIn('status', ['pending', 'confirmed'])
                ->sum('amount');
            $confirmedDebtCredits = (int) $locked->returns()
                ->where('settlement_method', 'debt_reduction')
                ->where('status', 'confirmed')
                ->sum('amount');
            $remaining = $locked->grand_total - $paid - $debtCredits;

            if ($remaining <= 0 || (int) $data['amount'] > $remaining) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi sisa tagihan pembelian.']);
            }

            $locked->payments()->create($data);
            $locked->update([
                'payment_status' => $this->paymentStatus($paid + $confirmedDebtCredits + (int) $data['amount'], $locked->grand_total),
            ]);
        }, attempts: 3);

        return back()->with('success', 'Pembayaran pemasok berhasil dicatat.');
    }

    private function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                'unique:suppliers,code'.($supplier ? ','.$supplier->id : ''),
            ],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function paymentStatus(int $paid, int $grandTotal): string
    {
        if ($paid >= $grandTotal) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }

    private function newPurchaseCode(): string
    {
        do {
            $code = 'PB-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Purchase::query()->where('purchase_code', $code)->exists());

        return $code;
    }
}
