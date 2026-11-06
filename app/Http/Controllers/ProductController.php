<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Services\ProductInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(ProductInventory $inventory): View
    {
        $products = Product::query()
            ->with(['category', 'variants.unit'])
            ->orderBy('name')
            ->get();
        $variantIds = $products->flatMap(fn (Product $product) => $product->variants)->pluck('id')->all();
        $stock = $inventory->onHandByVariant($variantIds);
        $physicalStock = $inventory->physicalOnHandByVariant($variantIds);

        $products->each(fn (Product $product) => $product->variants->each(function (ProductVariant $variant) use ($stock, $physicalStock): void {
            $variant->setAttribute('stock_on_hand', $stock->get($variant->id, 0));
            $variant->setAttribute('physical_stock', $physicalStock->get($variant->id, 0));
            $variant->setAttribute('is_below_minimum', $variant->stock_on_hand < (float) $variant->minimum_quantity);
        }));

        return view('products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ProductInventory $inventory): RedirectResponse
    {
        $this->normalizeBarcode($request);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:120', 'unique:products,sku'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'label' => ['required', 'string', 'max:120'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'barcode' => ['nullable', 'string', 'max:120', 'regex:/^[\x20-\x7E]+$/', 'unique:product_variants,barcode'],
            'selling_price' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'initial_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'batch_code' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'unit_cost' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ]);
        $this->assertBarcodeAvailable($data['barcode'] ?? null);
        $initialQuantity = (int) round((float) ($data['initial_quantity'] ?? 0) * 1000);

        if ($initialQuantity > 0 && ! filled($data['batch_code'] ?? null)) {
            throw ValidationException::withMessages(['batch_code' => 'Kode batch wajib diisi jika stok awal ditambahkan.']);
        }

        DB::transaction(function () use ($data, $initialQuantity, $inventory): void {
            $product = Product::create([
                'sku' => trim($data['sku']),
                'category_id' => $data['category_id'],
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
            ]);
            $variant = $product->variants()->create([
                'unit_id' => $data['unit_id'],
                'label' => trim($data['label']),
                'barcode' => $data['barcode'] ?? null,
                'selling_price' => $data['selling_price'],
                'minimum_quantity' => $data['minimum_quantity'],
            ]);

            if ($initialQuantity > 0) {
                $inventory->receive(
                    $variant->id,
                    trim($data['batch_code']),
                    $initialQuantity,
                    $data['expires_at'] ?? null,
                    isset($data['unit_cost']) ? (int) $data['unit_cost'] : null,
                    'Stok awal produk',
                );
            }
        }, attempts: 3);

        return back()->with('success', 'Produk dan varian pertama berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($product->id)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($product, $data): void {
            Product::query()->lockForUpdate()->findOrFail($product->id)->update([
                ...$data,
                'sku' => trim($data['sku']),
                'name' => trim($data['name']),
            ]);
        }, attempts: 3);

        return back()->with('success', 'Informasi produk berhasil diperbarui.');
    }

    public function storeVariant(Request $request, Product $product): RedirectResponse
    {
        $this->normalizeBarcode($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120', Rule::unique('product_variants', 'label')->where('product_id', $product->id)],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'barcode' => ['nullable', 'string', 'max:120', 'regex:/^[\x20-\x7E]+$/', 'unique:product_variants,barcode'],
            'selling_price' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);
        $this->assertBarcodeAvailable($data['barcode'] ?? null);
        DB::transaction(function () use ($product, $data): void {
            Product::query()->lockForUpdate()->findOrFail($product->id)
                ->variants()->create([...$data, 'label' => trim($data['label'])]);
        }, attempts: 3);

        return back()->with('success', 'Varian produk berhasil ditambahkan.');
    }

    public function updateVariant(Request $request, ProductVariant $productVariant): RedirectResponse
    {
        $this->normalizeBarcode($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120', Rule::unique('product_variants', 'label')->where('product_id', $productVariant->product_id)->ignore($productVariant->id)],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'barcode' => ['nullable', 'string', 'max:120', 'regex:/^[\x20-\x7E]+$/', Rule::unique('product_variants', 'barcode')->ignore($productVariant->id)],
            'selling_price' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);
        $this->assertBarcodeAvailable($data['barcode'] ?? null, $productVariant->id);
        DB::transaction(function () use ($productVariant, $data): void {
            ProductVariant::query()->lockForUpdate()->findOrFail($productVariant->id)
                ->update([...$data, 'label' => trim($data['label'])]);
        }, attempts: 3);

        return back()->with('success', 'Varian produk berhasil diperbarui.');
    }

    public function toggleProduct(Product $product): RedirectResponse
    {
        $enabled = DB::transaction(function () use ($product): bool {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $locked->update(['is_available' => ! $locked->is_available]);

            return $locked->is_available;
        }, attempts: 3);

        return back()->with('success', $enabled ? 'Produk diaktifkan.' : 'Produk disembunyikan dari katalog.');
    }

    public function toggleVariant(ProductVariant $productVariant): RedirectResponse
    {
        $enabled = DB::transaction(function () use ($productVariant): bool {
            $locked = ProductVariant::query()->lockForUpdate()->findOrFail($productVariant->id);
            $locked->update(['is_available' => ! $locked->is_available]);

            return $locked->is_available;
        }, attempts: 3);

        return back()->with('success', $enabled ? 'Varian diaktifkan.' : 'Varian disembunyikan dari katalog.');
    }

    public function receiveStock(Request $request, ProductVariant $productVariant, ProductInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'batch_code' => ['required', 'string', 'max:64', Rule::unique('product_stock_batches', 'batch_code')->where('product_variant_id', $productVariant->id)],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'unit_cost' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $inventory->receive(
            $productVariant->id,
            trim($data['batch_code']),
            (int) round((float) $data['quantity'] * 1000),
            $data['expires_at'] ?? null,
            isset($data['unit_cost']) ? (int) $data['unit_cost'] : null,
        );

        return back()->with('success', 'Stok varian berhasil ditambahkan.');
    }

    public function reconcileStock(Request $request, ProductVariant $productVariant, ProductInventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'counted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'reference' => ['required', 'string', 'max:48'],
        ]);
        $inventory->reconcilePhysicalCount(
            $productVariant->id,
            (int) round((float) $data['counted_quantity'] * 1000),
            trim($data['reference']),
        );

        return back()->with('success', 'Stok fisik varian berhasil direkonsiliasi.');
    }

    private function normalizeBarcode(Request $request): void
    {
        $request->merge(['barcode' => $request->filled('barcode') ? strtoupper(trim((string) $request->input('barcode'))) : null]);
    }

    private function assertBarcodeAvailable(?string $barcode, ?int $ignoreVariantId = null): void
    {
        if ($barcode !== null && Menu::query()->where('barcode', $barcode)->exists()) {
            throw ValidationException::withMessages(['barcode' => 'Barcode tersebut sudah digunakan oleh menu.']);
        }

        if ($barcode !== null && ProductVariant::query()
            ->where('barcode', $barcode)
            ->when($ignoreVariantId, fn ($query) => $query->where('id', '!=', $ignoreVariantId))
            ->exists()) {
            throw ValidationException::withMessages(['barcode' => 'Barcode tersebut sudah digunakan oleh varian produk lain.']);
        }
    }
}
