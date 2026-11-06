<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuVariant;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Services\CouponCalculator;
use App\Services\LoyaltyProgram;
use App\Services\ProductInventory;
use App\Services\ProductPromotionCalculator;
use App\Services\RecipeInventory;
use App\Services\TransactionChargeCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function menu(Request $request, RecipeInventory $inventory, ProductInventory $productInventory): View
    {
        $categories = Category::query()->orderBy('name')->get();
        $menus = Menu::query()
            ->with(['category', 'recipes', 'variants' => fn ($query) => $query->where('is_active', true)->with('recipes')])
            ->availableForSale()
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category_id', $request->integer('category')))
            ->when($request->filled('q'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();
        $orderingAvailable = CashierShift::query()->whereNull('closed_at')->exists();
        $inventoryAvailability = $inventory->availableServings($menus);
        $variantAvailability = $inventory->availableVariantServings($menus);
        $productVariants = ProductVariant::query()
            ->with(['product.category', 'unit'])
            ->where('is_available', true)
            ->whereHas('product', fn (Builder $query) => $query->where('is_available', true))
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('product', fn (Builder $products) => $products->where('category_id', $request->integer('category'))))
            ->when($request->filled('q'), fn (Builder $query) => $query->where(fn (Builder $search) => $search
                ->whereHas('product', fn (Builder $products) => $products->where('name', 'like', '%'.$request->string('q').'%'))
                ->orWhere('label', 'like', '%'.$request->string('q').'%')))
            ->orderBy('product_id')
            ->orderBy('label')
            ->get();
        $productStock = $productInventory->onHandByVariant($productVariants->pluck('id')->all());
        $initialCart = $this->restoreCart($request);

        $diningTable = DiningTable::query()->find($request->session()->get('dining_table_id'));

        return view('orders.menu', compact('categories', 'menus', 'orderingAvailable', 'inventoryAvailability', 'variantAvailability', 'productVariants', 'productStock', 'initialCart', 'diningTable'));
    }

    public function enterTable(Request $request, DiningTable $table): RedirectResponse
    {
        $request->session()->put('dining_table_id', $table->id);

        return redirect()->route('home')->with('success', 'Meja '.$table->number.' dipilih. Silakan pesan dari menu.');
    }

    public function quoteCoupon(Request $request, CouponCalculator $calculator, ProductPromotionCalculator $promotions, TransactionChargeCalculator $charges): JsonResponse
    {
        return $this->quote($request, $calculator, $promotions, $charges, app(LoyaltyProgram::class), false);
    }

    public function quotePos(Request $request, CouponCalculator $calculator, ProductPromotionCalculator $promotions, TransactionChargeCalculator $charges, LoyaltyProgram $loyalty): JsonResponse
    {
        return $this->quote($request, $calculator, $promotions, $charges, $loyalty, true);
    }

    private function quote(Request $request, CouponCalculator $calculator, ProductPromotionCalculator $promotions, TransactionChargeCalculator $charges, LoyaltyProgram $loyalty, bool $allowLoyalty): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'redeem_points' => [$allowLoyalty ? 'nullable' : 'prohibited', 'integer', 'min:0', 'max:999999999999'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_id' => ['nullable', 'integer', 'exists:menus,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.menu_variant_id' => ['nullable', 'integer', 'exists:menu_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);
        $items = $this->normalizeItems($data['items']);
        $menuIds = $items->where('type', 'menu')->pluck('id')->unique();
        $variantIds = $items->where('type', 'product')->pluck('id')->unique();
        $menuVariantIds = $items->where('type', 'menu')->pluck('menu_variant_id')->filter()->unique();
        $menus = Menu::query()->availableForSale()->whereIn('id', $menuIds)->get()->keyBy('id');
        $menuVariants = MenuVariant::query()->whereIn('id', $menuVariantIds)->where('is_active', true)->get()->keyBy('id');
        $variants = ProductVariant::query()
            ->with('product')
            ->where('is_available', true)
            ->whereHas('product', fn (Builder $query) => $query->where('is_available', true))
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        if ($menus->count() !== $menuIds->count() || $variants->count() !== $variantIds->count()) {
            throw ValidationException::withMessages(['items' => 'Ada menu atau produk yang tidak lagi tersedia.']);
        }

        $lines = $items->map(function (array $item) use ($menus, $variants, $menuVariants): array {
            $record = $item['type'] === 'menu' ? $menus->get($item['id']) : $variants->get($item['id']);
            $menuVariant = filled($item['menu_variant_id'] ?? null) ? $menuVariants->get($item['menu_variant_id']) : null;
            $menuHasVariants = $item['type'] === 'menu' && $record->variants()->where('is_active', true)->exists();

            if (($menuHasVariants && ! $menuVariant)
                || ($menuVariant && ($menuVariant->menu_id !== $record->id || $record->is_bundle))) {
                throw ValidationException::withMessages(['items' => 'Pilih varian yang tersedia untuk setiap menu yang memilikinya.']);
            }

            $price = (int) $record->selling_price + (int) ($menuVariant?->additional_price ?? 0);

            return [
                ...$item,
                'record' => $record,
                'menu_variant' => $menuVariant,
                'price' => $price,
                'key' => $item['type'].':'.$item['id'].':'.($menuVariant?->id ?? 0),
            ];
        });
        $subtotal = $lines->sum(fn (array $item): int => $item['price'] * $item['quantity']);

        if ($subtotal > 999999999999) {
            throw ValidationException::withMessages(['items' => 'Total pesanan melebihi batas transaksi.']);
        }

        $coupon = filled($data['code'] ?? null)
            ? Coupon::query()->where('is_active', true)
                ->whereRaw('upper(code) = ?', [Str::upper(trim($data['code']))])
                ->first()
            : null;

        if (filled($data['code'] ?? null) && ! $coupon) {
            throw ValidationException::withMessages(['code' => 'Kode kupon tidak ditemukan atau tidak aktif.']);
        }

        $phone = $this->normalizePhone($data['customer_phone'] ?? null);
        $customer = $phone ? Customer::query()->where('phone', $phone)->first() : null;
        $menuLines = $lines->where('type', 'menu')->map(fn (array $item): array => [
            'menu' => $item['record'],
            'quantity' => $item['quantity'],
            'price' => $item['price'],
            'key' => $item['key'],
        ])->values();
        $promotionQuote = $promotions->summarize(
            $menuLines,
            $promotions->calculate($menuLines->all(), $customer?->id),
        );
        $couponDiscount = $coupon ? $calculator->discount($coupon, $subtotal - $promotionQuote['discount']) : 0;
        $loyaltyQuote = $allowLoyalty
            ? $loyalty->quote($customer, (int) ($data['redeem_points'] ?? 0), $subtotal - $promotionQuote['discount'] - $couponDiscount)
            : ['balance' => 0, 'points' => 0, 'discount' => 0];
        $discount = $promotionQuote['discount'] + $couponDiscount + $loyaltyQuote['discount'];
        $chargeRows = $charges->calculate($subtotal - $discount);
        $chargeTotal = $chargeRows->sum('amount');
        $grandTotal = $subtotal - $discount + $chargeTotal;

        if ($grandTotal > 999999999999) {
            throw ValidationException::withMessages(['items' => 'Total pesanan melebihi batas transaksi.']);
        }

        return response()->json([
            'code' => $coupon?->code,
            'subtotal' => $subtotal,
            'promotion_discount' => $promotionQuote['discount'],
            'coupon_discount' => $couponDiscount,
            'loyalty_points_balance' => $loyaltyQuote['balance'],
            'loyalty_points_redeemed' => $loyaltyQuote['points'],
            'loyalty_discount' => $loyaltyQuote['discount'],
            'promotions' => $promotionQuote['lines'],
            'discount' => $discount,
            'charges' => $chargeRows,
            'charge_total' => $chargeTotal,
            'grand_total' => $grandTotal,
        ]);
    }

    public function storeOnline(Request $request, RecipeInventory $inventory, ProductInventory $productInventory, ProductPromotionCalculator $promotions, CouponCalculator $couponCalculator, TransactionChargeCalculator $charges, LoyaltyProgram $loyalty): RedirectResponse
    {
        $transaction = $this->createTransaction($request, false, $inventory, $productInventory, $promotions, $couponCalculator, $charges, $loyalty);

        return redirect()->route('orders.show', $transaction->invoice);
    }

    public function storePos(Request $request, RecipeInventory $inventory, ProductInventory $productInventory, ProductPromotionCalculator $promotions, CouponCalculator $couponCalculator, TransactionChargeCalculator $charges, LoyaltyProgram $loyalty): RedirectResponse
    {
        $transaction = $this->createTransaction($request, true, $inventory, $productInventory, $promotions, $couponCalculator, $charges, $loyalty);

        return redirect()->route('pos.orders.receipt', $transaction)
            ->with('success', 'Transaksi '.$transaction->invoice.' berhasil disimpan.')
            ->with('print_receipt', true);
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load(['details', 'charges']);

        return view('orders.show', compact('transaction'));
    }

    public function showReceipt(Transaction $transaction): View
    {
        abort_unless($transaction->status === 'paid', 404);
        $transaction->load(['details', 'charges', 'bankAccount', 'diningTable']);

        return view('pos.receipt', compact('transaction'));
    }

    public function markPaid(Request $request, Transaction $transaction, RecipeInventory $inventory, ProductInventory $productInventory, LoyaltyProgram $loyalty): RedirectResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,transfer'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'amount_paid' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        DB::transaction(function () use ($transaction, $data, $inventory, $productInventory, $loyalty): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->status === 'pending', 422, 'Hanya pesanan yang belum dibayar dapat diproses.');
            abort_if((int) $data['amount_paid'] < $locked->grand_total, 422, 'Jumlah pembayaran kurang dari total transaksi.');
            abort_if(
                $data['payment_method'] === 'transfer' && (int) $data['amount_paid'] !== $locked->grand_total,
                422,
                'Untuk pembayaran transfer, jumlah harus sama dengan total transaksi.',
            );
            $bankAccount = filled($data['bank_account_id'] ?? null)
                ? BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id'])
                : null;

            if ($bankAccount && ($data['payment_method'] !== 'transfer' || ! $bankAccount->is_active)) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'Pilih rekening aktif hanya untuk pembayaran transfer.',
                ]);
            }

            $recipeCost = $inventory->consume($locked);
            $productCost = $productInventory->consume($locked);
            $cogsComplete = $recipeCost['complete'] && $productCost['complete'];

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $data['payment_method'],
                'bank_account_id' => $bankAccount?->id,
                'amount_paid' => $data['amount_paid'],
                'change_due' => max(0, (int) $data['amount_paid'] - $locked->grand_total),
                'cogs_total' => $cogsComplete ? $recipeCost['total'] + $productCost['total'] : null,
                'cogs_complete' => $cogsComplete,
            ]);
            $loyalty->recordSale($locked);
        });

        return redirect()->route('pos.orders.receipt', $transaction)
            ->with('success', 'Pembayaran berhasil dicatat.')
            ->with('print_receipt', true);
    }

    public function updateKitchen(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate(['kitchen_status' => ['required', 'in:onprogress,success']]);
        $allowedTransitions = [
            'pending' => ['onprogress'],
            'onprogress' => ['success'],
            'success' => [],
        ];

        DB::transaction(function () use ($transaction, $data, $allowedTransitions): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->details()->whereNotNull('menu_id')->exists(), 422, 'Transaksi produk eceran tidak masuk ke alur dapur.');
            abort_unless(in_array($data['kitchen_status'], $allowedTransitions[$locked->kitchen_status] ?? [], true), 422, 'Perubahan status dapur tidak valid.');
            abort_if($locked->status === 'cancelled', 422, 'Pesanan yang dibatalkan tidak dapat diproses dapur.');
            $locked->update(['kitchen_status' => $data['kitchen_status']]);
        });

        return back()->with('success', 'Status dapur diperbarui.');
    }

    public function cancel(Transaction $transaction): RedirectResponse
    {
        DB::transaction(function () use ($transaction): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $hasMenuItems = $locked->details()->whereNotNull('menu_id')->exists();
            abort_unless($locked->status === 'pending' && (! $hasMenuItems || $locked->kitchen_status === 'pending'), 422, 'Hanya pesanan belum dibayar dan belum dikerjakan yang dapat dibatalkan.');
            $locked->update(['status' => 'cancelled', 'kitchen_status' => 'cancelled']);
        });

        return back()->with('success', 'Pesanan dibatalkan.');
    }

    private function createTransaction(Request $request, bool $isPos, RecipeInventory $inventory, ProductInventory $productInventory, ProductPromotionCalculator $promotions, CouponCalculator $couponCalculator, TransactionChargeCalculator $chargeCalculator, LoyaltyProgram $loyalty): Transaction
    {
        $data = $request->validate([
            'customer_name' => [$isPos ? 'nullable' : 'required', 'string', 'max:120'],
            'customer_phone' => [$isPos ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{7,30}$/'],
            'transaction_type' => ['required', 'in:dine_in,takeaway'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'redeem_points' => [$isPos ? 'nullable' : 'prohibited', 'integer', 'min:0', 'max:999999999999'],
            'dining_table_id' => [$isPos ? 'nullable' : 'prohibited', 'integer', 'exists:dining_tables,id'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.menu_id' => ['nullable', 'integer', 'exists:menus,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.menu_variant_id' => ['nullable', 'integer', 'exists:menu_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'payment_method' => [$isPos ? 'required' : 'prohibited', 'in:cash,transfer'],
            'bank_account_id' => [$isPos ? 'nullable' : 'prohibited', 'integer', 'exists:bank_accounts,id'],
            'amount_paid' => [$isPos ? 'required' : 'prohibited', 'integer', 'min:0', 'max:999999999999'],
        ], [
            'items.required' => 'Pilih setidaknya satu menu.',
        ]);

        $items = $this->normalizeItems($data['items']);

        return DB::transaction(function () use ($data, $items, $request, $isPos, $inventory, $productInventory, $promotions, $couponCalculator, $chargeCalculator, $loyalty): Transaction {
            $shiftQuery = CashierShift::query()->whereNull('closed_at');

            if ($isPos) {
                $shiftQuery->where('user_id', $request->user()->id);
            }

            $shift = $shiftQuery->lockForUpdate()->latest('opened_at')->first();

            if (! $shift) {
                throw ValidationException::withMessages([
                    'items' => $isPos
                        ? 'Buka shift kasir terlebih dahulu sebelum membuat transaksi.'
                        : 'Pemesanan online sedang tidak tersedia karena kasir belum membuka shift.',
                ]);
            }

            $menuIds = $items->where('type', 'menu')->pluck('id')->sort()->values();
            $variantIds = $items->where('type', 'product')->pluck('id')->sort()->values();
            $menus = Menu::query()
                ->whereIn('id', $menuIds)
                ->availableForSale()
                ->with('variants')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $menuVariantIds = $items->where('type', 'menu')->pluck('menu_variant_id')->filter()->unique()->sort()->values();
            $menuVariants = MenuVariant::query()
                ->whereIn('id', $menuVariantIds)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $variants = ProductVariant::query()
                ->with('product')
                ->whereIn('id', $variantIds)
                ->where('is_available', true)
                ->whereHas('product', fn (Builder $query) => $query->where('is_available', true))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($menus->count() !== $menuIds->count() || $variants->count() !== $variantIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Ada menu yang baru saja habis atau tidak tersedia. Segarkan halaman lalu coba kembali.',
                ]);
            }

            $lines = $items->map(function (array $item) use ($menus, $variants, $menuVariants): array {
                $record = $item['type'] === 'menu' ? $menus->get($item['id']) : $variants->get($item['id']);
                $menuVariant = filled($item['menu_variant_id'] ?? null) ? $menuVariants->get($item['menu_variant_id']) : null;
                $menuHasVariants = $item['type'] === 'menu' && $record->variants->contains(fn (MenuVariant $variant) => $variant->is_active);

                if (($menuHasVariants && ! $menuVariant)
                    || ($menuVariant && ($menuVariant->menu_id !== $record->id || $record->is_bundle))) {
                    throw ValidationException::withMessages(['items' => 'Pilih varian yang tersedia untuk setiap menu yang memilikinya.']);
                }

                $quantity = (int) $item['quantity'];
                $price = (int) $record->selling_price + (int) ($menuVariant?->additional_price ?? 0);
                $lineName = $item['type'] === 'menu'
                    ? $record->name.($menuVariant ? ' · '.$menuVariant->name : '')
                    : $record->product->name.' · '.$record->label;

                return [
                    'type' => $item['type'],
                    'id' => $item['id'],
                    'record' => $record,
                    'menu' => $item['type'] === 'menu' ? $record : null,
                    'menu_variant' => $menuVariant,
                    'key' => $item['type'].':'.$item['id'].':'.($menuVariant?->id ?? 0),
                    'item_name' => $lineName,
                    'quantity' => $quantity,
                    'price' => $price,
                    'line_total' => $price * $quantity,
                ];
            });
            $subtotal = $lines->sum('line_total');

            if ($subtotal > 999999999999) {
                throw ValidationException::withMessages(['items' => 'Total pesanan melebihi batas transaksi.']);
            }

            $tableId = $isPos
                ? (isset($data['dining_table_id']) ? (int) $data['dining_table_id'] : null)
                : $request->session()->get('dining_table_id');
            $table = $tableId
                ? DiningTable::query()->lockForUpdate()->findOrFail($tableId)
                : null;
            $coupon = filled($data['coupon_code'] ?? null)
                ? Coupon::query()->where('is_active', true)
                    ->whereRaw('upper(code) = ?', [Str::upper(trim($data['coupon_code']))])
                    ->lockForUpdate()
                    ->first()
                : null;

            if (filled($data['coupon_code'] ?? null) && ! $coupon) {
                throw ValidationException::withMessages(['coupon_code' => 'Kode kupon tidak ditemukan atau tidak aktif.']);
            }
            $productInventory->assertSellable($lines->where('type', 'product')->map(fn ($line) => [
                'product_variant_id' => $line['id'],
                'quantity' => $line['quantity'],
            ])->all());

            if (! $isPos) {
                $menuLines = $lines->where('type', 'menu');
                $inventory->assertAvailable($menuLines->map(fn ($line) => [
                    'menu_id' => $line['menu']->id,
                    'menu_variant_id' => $line['menu_variant']?->id,
                    'quantity' => $line['quantity'],
                ])->all());
            }

            $phone = $this->normalizePhone($data['customer_phone'] ?? null);

            if ($phone !== null && (strlen($phone) < 8 || strlen($phone) > 15)) {
                throw ValidationException::withMessages(['customer_phone' => 'Masukkan nomor telepon yang valid.']);
            }

            $customer = $phone
                ? Customer::query()->where('phone', $phone)->lockForUpdate()->first()
                : null;

            if ($phone && ! $customer) {
                $customer = Customer::create([
                    'name' => ($data['customer_name'] ?? null) ?: 'Pelanggan kasir',
                    'phone' => $phone,
                ]);
            } elseif ($customer && filled($data['customer_name'] ?? null) && $customer->name !== $data['customer_name']) {
                $customer->update(['name' => $data['customer_name']]);
            }

            $menuLines = $lines->where('type', 'menu')->values();
            $promotionBreakdown = $promotions->calculate($menuLines->map(fn (array $line): array => [
                'menu' => $line['menu'],
                'quantity' => $line['quantity'],
                'price' => $line['price'],
                'key' => $line['key'],
            ])->all(), $customer?->id);
            $promotionDiscount = array_sum(array_column($promotionBreakdown, 'discount'));
            $couponDiscount = $coupon ? $couponCalculator->discount($coupon, $subtotal - $promotionDiscount) : 0;
            $redeemedPoints = $isPos ? (int) ($data['redeem_points'] ?? 0) : 0;
            $loyaltyQuote = $loyalty->quote(
                $isPos ? $customer : null,
                $redeemedPoints,
                $subtotal - $promotionDiscount - $couponDiscount,
            );
            $discount = $promotionDiscount + $couponDiscount + $loyaltyQuote['discount'];
            $chargeRows = $chargeCalculator->calculate($subtotal - $discount, lock: true);
            $chargeTotal = $chargeRows->sum('amount');
            $grandTotal = $subtotal - $discount + $chargeTotal;

            if ($grandTotal > 999999999999) {
                throw ValidationException::withMessages(['items' => 'Total pesanan melebihi batas transaksi.']);
            }
            $amountPaid = $isPos ? (int) $data['amount_paid'] : 0;

            if ($isPos && $data['payment_method'] === 'cash' && $amountPaid < $grandTotal) {
                throw ValidationException::withMessages(['amount_paid' => 'Uang yang diterima kurang dari total belanja.']);
            }

            if ($isPos && $data['payment_method'] === 'transfer' && $amountPaid !== $grandTotal) {
                throw ValidationException::withMessages(['amount_paid' => 'Untuk pembayaran transfer, jumlah harus sama dengan total belanja.']);
            }

            $bankAccount = filled($data['bank_account_id'] ?? null)
                ? BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id'])
                : null;

            if ($bankAccount && ($data['payment_method'] !== 'transfer' || ! $bankAccount->is_active)) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'Pilih rekening aktif hanya untuk pembayaran transfer.',
                ]);
            }

            $lines = $lines->map(function (array $line) use ($promotionBreakdown): array {
                $promotion = $line['type'] === 'menu'
                    ? ($promotionBreakdown[$line['key']] ?? ['discount' => 0, 'name' => null])
                    : ['discount' => 0, 'name' => null];
                $line['promotion_discount'] = $promotion['discount'];
                $line['promotion_name'] = $promotion['name'];

                return $line;
            });

            $transaction = Transaction::create([
                'invoice' => 'PJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
                'cashier_shift_id' => $shift->id,
                'customer_id' => $customer?->id,
                'dining_table_id' => $table?->id,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'customer_name' => ($data['customer_name'] ?? null) ?: 'Pelanggan kasir',
                'customer_phone' => $phone,
                'transaction_type' => $table ? 'dine_in' : $data['transaction_type'],
                'status' => $isPos ? 'paid' : 'pending',
                'paid_at' => $isPos ? now() : null,
                'kitchen_status' => $menuLines->isEmpty() ? 'success' : 'pending',
                'payment_method' => $isPos ? $data['payment_method'] : null,
                'bank_account_id' => $bankAccount?->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'promotion_discount' => $promotionDiscount,
                'coupon_discount' => $couponDiscount,
                'loyalty_points_redeemed' => $redeemedPoints,
                'loyalty_discount' => $loyaltyQuote['discount'],
                'grand_total' => $grandTotal,
                'amount_paid' => $amountPaid,
                'change_due' => $isPos ? max(0, $amountPaid - $grandTotal) : 0,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($table) {
                $table->update(['status' => 'occupied']);
            }

            foreach ($lines as $line) {
                $transaction->details()->create([
                    'item_type' => $line['type'],
                    'menu_id' => $line['type'] === 'menu' ? $line['id'] : null,
                    'menu_variant_id' => $line['type'] === 'menu' ? $line['menu_variant']?->id : null,
                    'product_variant_id' => $line['type'] === 'product' ? $line['id'] : null,
                    'item_name' => $line['item_name'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'promotion_discount' => $line['promotion_discount'],
                    'promotion_name' => $line['promotion_name'],
                ]);
            }

            foreach ($chargeRows as $charge) {
                $transaction->charges()->create($charge);
            }

            $inventory->snapshot($transaction);

            if ($isPos) {
                $loyalty->recordSale($transaction, $redeemedPoints);
                $recipeCost = $inventory->consume($transaction);
                $productCost = $productInventory->consume($transaction);
                $cogsComplete = $recipeCost['complete'] && $productCost['complete'];
                $transaction->update([
                    'cogs_total' => $cogsComplete ? $recipeCost['total'] + $productCost['total'] : null,
                    'cogs_complete' => $cogsComplete,
                ]);
            }

            return $transaction;
        }, attempts: 3);
    }

    private function restoreCart(Request $request): array
    {
        $oldItems = $request->session()->getOldInput('items', []);

        if (! is_array($oldItems)) {
            return [];
        }

        $items = collect($oldItems)->filter(fn ($item) => is_array($item)
            && (filter_var($item['menu_id'] ?? null, FILTER_VALIDATE_INT) || filter_var($item['product_variant_id'] ?? null, FILTER_VALIDATE_INT)));
        $menuIds = $items->pluck('menu_id')->filter()->unique();
        $variantIds = $items->pluck('product_variant_id')->filter()->unique();
        $menuVariantIds = $items->pluck('menu_variant_id')->filter()->unique();
        $menus = Menu::query()->with('variants')->whereIn('id', $menuIds)->get()->keyBy('id');
        $menuVariants = MenuVariant::query()->whereIn('id', $menuVariantIds)->where('is_active', true)->get()->keyBy('id');
        $variants = ProductVariant::query()->with('product')->whereIn('id', $variantIds)->get()->keyBy('id');

        return $items->map(function (array $item) use ($menus, $variants, $menuVariants): ?array {
            $type = filled($item['product_variant_id'] ?? null) ? 'product' : 'menu';
            $id = (int) ($type === 'product' ? $item['product_variant_id'] : $item['menu_id']);
            $record = $type === 'product' ? $variants->get($id) : $menus->get($id);
            $menuVariant = $type === 'menu' && filled($item['menu_variant_id'] ?? null)
                ? $menuVariants->get((int) $item['menu_variant_id'])
                : null;
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);

            if (! $record || $quantity === false || $quantity < 1 || $quantity > 20) {
                return null;
            }
            if ($type === 'menu' && $record->variants->contains(fn (MenuVariant $variant) => $variant->is_active)
                && (! $menuVariant || $menuVariant->menu_id !== $record->id)) {
                return null;
            }

            return [
                'type' => $type,
                'id' => $id,
                'menu_variant_id' => $menuVariant?->id,
                'name' => $type === 'product'
                    ? $record->product->name.' · '.$record->label
                    : $record->name.($menuVariant ? ' · '.$menuVariant->name : ''),
                'price' => (int) $record->selling_price + (int) ($menuVariant?->additional_price ?? 0),
                'quantity' => $quantity,
            ];
        })->filter()->values()->all();
    }

    private function normalizeItems(array $items): Collection
    {
        $normalized = collect();
        $seen = [];

        foreach ($items as $index => $item) {
            $hasMenu = filled($item['menu_id'] ?? null);
            $hasVariant = filled($item['product_variant_id'] ?? null);
            $hasMenuVariant = filled($item['menu_variant_id'] ?? null);

            if ($hasMenu === $hasVariant) {
                throw ValidationException::withMessages(["items.$index" => 'Setiap baris harus berisi satu menu atau satu varian produk.']);
            }
            if ($hasMenuVariant && ! $hasMenu) {
                throw ValidationException::withMessages(["items.$index" => 'Varian menu hanya dapat digunakan pada item menu.']);
            }

            $type = $hasMenu ? 'menu' : 'product';
            $id = (int) ($hasMenu ? $item['menu_id'] : $item['product_variant_id']);
            $menuVariantId = $hasMenuVariant ? (int) $item['menu_variant_id'] : null;
            $key = $type.':'.$id.':'.($menuVariantId ?? 0);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["items.$index" => 'Item yang sama tidak boleh dimasukkan dua kali.']);
            }

            $seen[$key] = true;
            $normalized->push([
                'type' => $type,
                'id' => $id,
                'menu_variant_id' => $menuVariantId,
                'quantity' => (int) $item['quantity'],
            ]);
        }

        return $normalized;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
