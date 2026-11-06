<?php

namespace Tests\Feature;

use App\Models\BarcodeTemplate;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Material;
use App\Models\Menu;
use App\Models\MenuRecipe;
use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductInventory;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailProductFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_variant_stock_and_only_managers_manage_retail_products(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = Category::create(['name' => 'Minuman kemasan']);
        $unit = Unit::create(['name' => 'botol']);

        $this->actingAs($manager)->get(route('pos.products.index'))->assertOk()->assertSee('Produk eceran');
        $this->post(route('pos.products.store'), [
            'sku' => 'BOTOL-001',
            'category_id' => $category->id,
            'name' => 'Air mineral',
            'description' => 'Air mineral kemasan',
            'label' => '600 ml',
            'unit_id' => $unit->id,
            'barcode' => 'air-600',
            'selling_price' => 5000,
            'minimum_quantity' => 2,
            'initial_quantity' => 5,
            'batch_code' => 'AIR-INITIAL',
            'unit_cost' => 2500,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $variant = ProductVariant::query()->with('product')->firstOrFail();
        $this->assertSame('BOTOL-001', $variant->product->sku);
        $this->assertSame('AIR-600', $variant->barcode);
        $this->assertDatabaseHas('product_stock_batches', [
            'product_variant_id' => $variant->id,
            'batch_code' => 'AIR-INITIAL',
            'unit_cost' => 2500,
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'product_stock_batch_id' => ProductStockBatch::query()->value('id'),
            'type' => 'in',
            'quantity' => '5.000',
            'cost_amount' => 12500,
        ]);

        $this->actingAs($manager)->post(route('pos.products.variants.store', $variant->product), [
            'label' => '1.5 liter',
            'unit_id' => $unit->id,
            'barcode' => '',
            'selling_price' => 9000,
            'minimum_quantity' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, ProductVariant::query()->where('product_id', $variant->product_id)->count());

        $template = BarcodeTemplate::query()->create([
            'name' => 'Label produk',
            'paper_type' => 'roll',
            'paper_width_mm' => 60,
            'paper_height_mm' => null,
            'label_width_mm' => 40,
            'label_height_mm' => 30,
            'gap_horizontal_mm' => 0,
            'gap_vertical_mm' => 0,
            'margin_top_mm' => 0,
            'margin_left_mm' => 0,
            'columns' => 1,
            'rows' => 1,
        ]);
        $this->get(route('pos.barcode-templates.print', [
            'template_id' => $template->id,
            'items' => [['item' => 'product:'.$variant->id, 'quantity' => 2]],
        ]))->assertOk()->assertSee('data-label-barcode="AIR-600"', false)->assertSee('Air mineral · 600 ml');

        $this->actingAs($cashier)->get(route('pos.products.index'))->assertForbidden();
        $this->post(route('pos.products.store'), [])->assertForbidden();
    }

    public function test_online_mixed_order_is_paid_at_pos_and_consumes_separate_fifo_stock_with_combined_cogs(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = Category::create(['name' => 'Kedai']);
        $gram = Unit::create(['name' => 'gram']);
        $bottle = Unit::create(['name' => 'botol']);
        $material = Material::create(['name' => 'Biji kopi', 'unit_id' => $gram->id, 'minimum_quantity' => 0]);
        $menu = Menu::create([
            'category_id' => $category->id,
            'name' => 'Kopi hitam',
            'selling_price' => 8000,
            'is_available' => true,
        ]);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 2]);
        app(RecipeInventory::class)->receive($material->id, 'COFFEE-01', 10000, null, 'Stok masuk', '1000');

        $product = Product::create(['sku' => 'BOTOL-002', 'category_id' => $category->id, 'name' => 'Teh botol', 'is_available' => true]);
        $variant = $product->variants()->create([
            'unit_id' => $bottle->id,
            'label' => '350 ml',
            'barcode' => 'TEH-350',
            'selling_price' => 12000,
            'minimum_quantity' => 1,
        ]);
        app(ProductInventory::class)->receive($variant->id, 'TEH-01', 5000, null, 2500);
        CashierShift::create([
            'user_id' => $cashier->id,
            'starting_cash' => 100000,
            'opened_at' => now(),
        ]);

        $this->get(route('home'))->assertOk()->assertSee('Produk eceran')->assertSee('Teh botol · 350 ml');
        $this->post(route('orders.store'), [
            'customer_name' => 'Rina',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
                ['product_variant_id' => $variant->id, 'quantity' => 2],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $sale = Transaction::query()->with('details')->firstOrFail();
        $this->assertSame('pending', $sale->status);
        $this->assertSame('pending', $sale->kitchen_status);
        $this->assertSame(5.0, app(ProductInventory::class)->onHandByVariant([$variant->id])->get($variant->id));
        $this->assertSame('product', $sale->details->first(fn ($detail) => $detail->item_type === 'product')->item_type);

        $this->actingAs($cashier)->patch(route('pos.orders.paid', $sale), [
            'payment_method' => 'cash',
            'amount_paid' => $sale->grand_total,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(7000, $sale->fresh()->cogs_total);
        $this->assertTrue($sale->fresh()->cogs_complete);
        $this->assertSame(3.0, app(ProductInventory::class)->onHandByVariant([$variant->id])->get($variant->id));
        $this->assertDatabaseHas('product_stock_movements', [
            'transaction_id' => $sale->id,
            'type' => 'out',
            'quantity' => '2.000',
            'cost_amount' => 5000,
        ]);
        $this->actingAs($manager)->get(route('pos.reports.index', [
            'report' => 'profit-loss',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()->assertSee('Rp7.000');
    }

    public function test_retail_online_order_checks_stock_and_skips_kitchen_flow(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = Category::create(['name' => 'Perlengkapan']);
        $unit = Unit::create(['name' => 'pcs']);
        $product = Product::create(['sku' => 'CUP-001', 'category_id' => $category->id, 'name' => 'Gelas', 'is_available' => true]);
        $variant = $product->variants()->create([
            'unit_id' => $unit->id,
            'label' => 'Satu buah',
            'selling_price' => 3000,
            'minimum_quantity' => 0,
        ]);
        app(ProductInventory::class)->receive($variant->id, 'CUP-01', 1000, null, 1000);
        CashierShift::create(['user_id' => $cashier->id, 'starting_cash' => 0, 'opened_at' => now()]);

        $payload = [
            'customer_name' => 'Dina',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
        ];
        $this->post(route('orders.store'), [...$payload, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]]])
            ->assertSessionHasErrors('items');
        $this->assertSame(0, Transaction::query()->count());

        $this->post(route('orders.store'), [...$payload, 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $sale = Transaction::query()->firstOrFail();
        $this->assertSame('success', $sale->kitchen_status);
        $this->get(route('orders.show', $sale->invoice))->assertOk()->assertSee('Tidak perlu dapur');

        $this->actingAs($cashier)->patch(route('pos.orders.cancel', $sale))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame(1.0, app(ProductInventory::class)->onHandByVariant([$variant->id])->get($variant->id));
    }

    public function test_cashier_can_sell_a_retail_variant_directly_from_pos(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $category = Category::create(['name' => 'Makanan kemasan']);
        $unit = Unit::create(['name' => 'pak']);
        $product = Product::create(['sku' => 'SNACK-001', 'category_id' => $category->id, 'name' => 'Biskuit', 'is_available' => true]);
        $variant = $product->variants()->create([
            'unit_id' => $unit->id,
            'label' => 'Original',
            'selling_price' => 7000,
            'minimum_quantity' => 0,
        ]);
        app(ProductInventory::class)->receive($variant->id, 'BISKUIT-01', 3000, null, 3000);
        CashierShift::create(['user_id' => $cashier->id, 'starting_cash' => 50000, 'opened_at' => now()]);

        $response = $this->actingAs($cashier)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 14000,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
        ]);

        $sale = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('pos.orders.receipt', $sale))->assertSessionHasNoErrors();
        $this->assertSame('paid', $sale->status);
        $this->assertSame(6000, $sale->cogs_total);
        $this->assertTrue($sale->cogs_complete);
        $this->assertSame(1.0, app(ProductInventory::class)->onHandByVariant([$variant->id])->get($variant->id));
    }

    public function test_supplier_purchase_and_replacement_return_manage_retail_batches(): void
    {
        $cashier = User::factory()->create(['role' => 'manager']);
        $supplier = Supplier::create(['code' => 'SUP-RETAIL', 'name' => 'Pemasok eceran']);
        $category = Category::create(['name' => 'Makanan kemasan']);
        $unit = Unit::create(['name' => 'pak']);
        $product = Product::create(['sku' => 'SNACK-002', 'category_id' => $category->id, 'name' => 'Keripik', 'is_available' => true]);
        $variant = $product->variants()->create([
            'unit_id' => $unit->id,
            'label' => 'Pedas',
            'selling_price' => 8000,
            'minimum_quantity' => 0,
        ]);

        $this->actingAs($cashier)->post(route('pos.purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => today()->toDateString(),
            'purchase_type' => 'products',
            'items' => [[
                'item_ref' => 'product:'.$variant->id,
                'quantity' => '5',
                'unit_cost' => 3000,
                'expires_at' => '',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $purchase = Purchase::query()->with('items')->firstOrFail();
        $this->assertSame('products', $purchase->purchase_type);
        $this->assertSame('product', $purchase->items->firstOrFail()->item_type);
        $this->assertNull($purchase->items->firstOrFail()->material_id);
        $this->actingAs($cashier)->patch(route('pos.purchases.confirm', $purchase))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($cashier)->patch(route('pos.purchases.receive', $purchase))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(5.0, app(ProductInventory::class)->onHandByVariant([$variant->id])->get($variant->id));

        $purchaseItem = $purchase->items->firstOrFail();
        $returnPayload = [
            'purchase_id' => $purchase->id,
            'return_date' => today()->toDateString(),
            'settlement_method' => 'replacement',
            'items' => [[
                'purchase_item_id' => $purchaseItem->id,
                'quantity' => '2',
                'reason' => 'Kemasan rusak',
                'replacement_expires_at' => '',
            ]],
        ];
        $this->actingAs($cashier)->post(route('pos.purchase-returns.store'), $returnPayload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $return = PurchaseReturn::query()->firstOrFail();
        $this->actingAs($cashier)->patch(route('pos.purchase-returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(5.0, app(ProductInventory::class)->onHandByVariant([$variant->id])->get($variant->id));
        $this->assertDatabaseHas('product_stock_batches', [
            'product_variant_id' => $variant->id,
            'batch_code' => 'PB-'.$purchase->purchase_code.'-'.$purchaseItem->id,
            'unit_cost' => 3000,
        ]);
        $this->assertDatabaseHas('product_stock_batches', [
            'product_variant_id' => $variant->id,
            'batch_code' => 'PBRT-'.$return->return_code.'-'.$return->details()->value('id'),
            'unit_cost' => 3000,
        ]);
        $this->assertDatabaseHas('product_stock_movements', [
            'type' => 'out',
            'quantity' => '2.000',
            'description' => 'Retur pembelian '.$purchase->purchase_code.' ('.$return->return_code.')',
        ]);
    }

    public function test_purchase_document_cannot_mix_recipe_materials_and_retail_products(): void
    {
        $cashier = User::factory()->create(['role' => 'manager']);
        $supplier = Supplier::create(['code' => 'SUP-MIX', 'name' => 'Pemasok campuran']);
        $unit = Unit::create(['name' => 'pcs']);
        $material = Material::create(['name' => 'Gula', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $product = Product::create([
            'sku' => 'MIX-001',
            'category_id' => Category::create(['name' => 'Campuran'])->id,
            'name' => 'Camilan',
            'is_available' => true,
        ]);
        $variant = $product->variants()->create([
            'unit_id' => $unit->id,
            'label' => 'Satuan',
            'selling_price' => 1000,
            'minimum_quantity' => 0,
        ]);

        $this->actingAs($cashier)->from(route('pos.purchases.index'))->post(route('pos.purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => today()->toDateString(),
            'purchase_type' => 'materials',
            'items' => [
                ['item_ref' => 'material:'.$material->id, 'quantity' => 1, 'unit_cost' => 1000],
                ['item_ref' => 'product:'.$variant->id, 'quantity' => 1, 'unit_cost' => 1000],
            ],
        ])->assertRedirect(route('pos.purchases.index'))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('purchases', 0);
    }
}
