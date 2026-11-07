<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\Material;
use App\Models\MaterialStockBatch;
use App\Models\Menu;
use App\Models\MenuRecipe;
use App\Models\MenuVariant;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_order_uses_server_menu_prices_and_waits_for_payment(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();

        $response = $this->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ]);

        $transaction = Transaction::query()->with('details')->firstOrFail();
        $response->assertRedirect(route('orders.show', $transaction->invoice));
        $this->assertSame(44000, $transaction->subtotal);
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('pending', $transaction->kitchen_status);
        $this->assertSame(2, $transaction->details->first()->quantity);
        $this->assertSame(22000, $transaction->details->first()->price);
        $this->get(route('orders.show', $transaction->invoice))->assertOk()->assertSee('Antre dapur');
        $this->actingAs($user)->getJson(route('pos.orders.pending-count'))->assertOk()->assertJson(['count' => 1]);
    }

    public function test_all_active_kitchen_orders_are_completed_without_changing_payment(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $menu = $this->menu(22000);
        $user = $this->openShift();
        for ($i = 0; $i < 13; $i++) {
            $this->post(route('orders.store'), [
                'customer_name' => 'Pelanggan '.$i,
                'customer_phone' => '08123456789',
                'transaction_type' => 'takeaway',
                'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
            ])->assertRedirect();
        }
        $orders = Transaction::query()->orderBy('id')->get();
        $orders[0]->update(['kitchen_status' => 'onprogress']);
        $orders[1]->update(['status' => 'cancelled', 'kitchen_status' => 'cancelled']);
        $orders[2]->update(['kitchen_status' => 'success']);
        $orders[3]->update(['status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($user)->patch(route('pos.orders.kitchen.complete-all'))
            ->assertRedirect(route('pos.index', ['module' => 'pesanan']))
            ->assertSessionHas('success', '11 pesanan dapur ditandai selesai. Status pembayaran tidak berubah.');

        $this->assertSame(11, Transaction::query()->where('status', 'pending')->where('kitchen_status', 'success')->count());
        $this->assertSame('paid', $orders[3]->fresh()->status);
        $this->assertSame('success', $orders[3]->fresh()->kitchen_status);
        $this->assertSame('cancelled', $orders[1]->fresh()->kitchen_status);
        $this->assertSame(1, $user->notifications()->where('data', 'like', '%11 pesanan%')->count());

        $this->patch(route('pos.orders.kitchen.complete-all'))->assertSessionHas('success', '0 pesanan dapur ditandai selesai. Status pembayaran tidak berubah.');
        $this->assertSame(1, $user->notifications()->where('data', 'like', '%11 pesanan%')->count());
    }

    public function test_public_menu_allows_adding_items_before_login_and_hides_cart_in_a_dialog(): void
    {
        $this->menu(22000);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-open-cart', false)
            ->assertSee('data-cart-dialog', false)
            ->assertSee('data-checkout-dialog', false)
            ->assertSee('data-item-type="menu"', false)
            ->assertSee('data-add-menu', false)
            ->assertDontSee('data-add-menu disabled', false)
            ->assertDontSee('Bluetooth web')
            ->assertDontSee('data-printer-dialog', false)
            ->assertDontSee('Buka kasir');
    }

    public function test_authenticated_homepage_displays_staff_printer_and_cashier_controls(): void
    {
        $this->menu(22000);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get(route('home'))
            ->assertOk()
            ->assertSee('Bluetooth web')
            ->assertSee('data-printer-dialog', false)
            ->assertSee('Buka kasir');
    }

    public function test_dashboard_navigation_has_no_duplicate_module_grid_and_points_to_summary(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->get(route('pos.index', ['module' => 'kasir']))
            ->assertOk()
            ->assertDontSee('data-pos-module-link="dashboard"', false)
            ->assertSee('data-dashboard-toggle', false)
            ->assertSee('data-pos-module="ringkasan"', false)
            ->assertSeeInOrder(['<span>Ringkasan</span>', '<span>Audit stok</span>', '<span>Cycle count</span>'], false)
            ->assertDontSee('Dashboard modul')
            ->assertDontSee('data-module-dashboard', false);
    }

    public function test_customer_cart_remains_available_when_no_menu_matches(): void
    {
        $this->menu(22000);

        $this->get(route('home', ['q' => 'tidak-ada-menu-yang-cocok']))
            ->assertOk()
            ->assertSee('Belum ada menu atau produk yang cocok')
            ->assertSee('data-open-cart', false)
            ->assertSee('data-cart-dialog', false);
    }

    public function test_customer_and_pos_catalogs_search_menu_names_and_filter_by_category(): void
    {
        $coffee = $this->menu(22000);
        $teaCategory = Category::create(['name' => 'Teh']);
        Menu::create([
            'category_id' => $teaCategory->id,
            'name' => 'Teh Lemon',
            'description' => 'Teh segar.',
            'selling_price' => 18000,
            'is_available' => true,
        ]);

        $this->get(route('home', ['q' => 'Teh', 'category' => $teaCategory->id]))
            ->assertOk()
            ->assertSee('Teh Lemon')
            ->assertSee('aria-label="Kategori Teh"', false)
            ->assertDontSee('Kopi Susu');

        $cashier = User::factory()->create(['role' => 'cashier']);
        CashierShift::create([
            'user_id' => $cashier->id,
            'starting_cash' => 100000,
            'opened_at' => now(),
        ]);
        $posResponse = $this->actingAs($cashier)->get(route('pos.index', [
            'module' => 'kasir',
            'menu_q' => 'Kopi',
            'menu_category' => $coffee->category_id,
        ]));
        $posResponse->assertOk()
            ->assertSee('Kopi Susu')
            ->assertSee('<h3>Kopi</h3>', false);
        $posResponse->assertDontSee('data-item-name="Teh Lemon"', false);
    }

    public function test_menu_variant_price_and_additional_recipe_are_snapshotted_and_consumed(): void
    {
        $menu = $this->menu(22000);
        $cashier = $this->openShift();
        $unit = Unit::create(['name' => 'ml']);
        $material = Material::create(['name' => 'Susu varian', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $batch = MaterialStockBatch::create(['material_id' => $material->id, 'batch_code' => 'VARIANT-STOCK', 'unit_cost' => 100]);
        $batch->movements()->create([
            'type' => 'in',
            'quantity' => 100,
            'description' => 'Stok varian',
            'unit_cost' => 100,
            'cost_amount' => 10000,
        ]);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 20]);
        $variant = MenuVariant::create([
            'menu_id' => $menu->id,
            'name' => 'Large',
            'additional_price' => 6000,
            'is_active' => true,
        ]);
        $variant->recipes()->create(['material_id' => $material->id, 'quantity' => 10]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-item-variants', false)
            ->assertSee('Large');
        $this->actingAs($cashier)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('data-menu-variant-dialog', false)
            ->assertSee('Large');

        $quote = $this->postJson(route('coupons.quote'), [
            'items' => [[
                'menu_id' => $menu->id,
                'menu_variant_id' => $variant->id,
                'quantity' => 1,
            ]],
        ])->assertOk()->assertJsonPath('subtotal', 28000)->assertJsonPath('grand_total', 28000);

        $this->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [[
                'menu_id' => $menu->id,
                'menu_variant_id' => $variant->id,
                'quantity' => 1,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $transaction = Transaction::query()->with('details')->firstOrFail();
        $detail = $transaction->details->firstOrFail();
        $this->assertSame(28000, $transaction->subtotal);
        $this->assertSame('Kopi Susu · Large', $detail->item_name);
        $this->assertSame(28000, $detail->price);

        $this->actingAs($cashier)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'cash',
            'amount_paid' => 28000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('transaction_recipe_details', [
            'transaction_detail_id' => $detail->id,
            'material_id' => $material->id,
            'quantity' => 30,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'material_stock_batch_id' => $batch->id,
            'transaction_id' => $transaction->id,
            'type' => 'out',
            'quantity' => 30,
            'cost_amount' => 3000,
        ]);
    }

    public function test_menu_with_active_variants_requires_customer_to_select_one(): void
    {
        $menu = $this->menu(22000);
        $this->openShift();
        MenuVariant::create(['menu_id' => $menu->id, 'name' => 'Medium', 'additional_price' => 0]);

        $this->from(route('home'))->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect(route('home'))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_employee_consumption_rejects_inactive_employee_accounts(): void
    {
        $cashier = $this->openShift();
        $menu = $this->menu(22000);
        $inactiveEmployee = User::factory()->create(['role' => 'cashier', 'is_active' => false]);

        $this->actingAs($cashier)->from(route('pos.index'))->post(route('pos.employee-consumptions.store'), [
            'employee_id' => $inactiveEmployee->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect(route('pos.index'))->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_employee_consumption_uses_cogs_without_payment_and_calendar_day_target(): void
    {
        $this->travelTo(Carbon::parse('2028-02-10 10:00:00'));
        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create([
            'user_id' => $manager->id,
            'starting_cash' => 0,
            'opened_at' => now(),
        ]);
        $employee = User::factory()->create(['role' => 'cashier', 'name' => 'Karyawan Uji']);
        $menu = $this->menu(22000);
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create(['name' => 'Biji konsumsi', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $batch = MaterialStockBatch::create(['material_id' => $material->id, 'batch_code' => 'EMPLOYEE-COFFEE', 'unit_cost' => 50]);
        $batch->movements()->create([
            'type' => 'in',
            'quantity' => 100,
            'description' => 'Stok konsumsi karyawan',
            'unit_cost' => 50,
            'cost_amount' => 5000,
        ]);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 10]);
        $category = ExpenseCategory::create(['name' => 'Biaya operasional uji']);
        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'name' => 'Biaya rutin uji',
        ]);
        Expense::create([
            'expense_code' => 'BY-LEAP-2028',
            'expense_date' => today(),
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'created_by' => $manager->id,
            'amount' => 29000,
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($manager)->post(route('pos.employee-consumptions.store'), [
            'employee_id' => $employee->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect(route('pos.index', ['module' => 'konsumsi']))->assertSessionHasNoErrors();

        $consumption = Transaction::query()->where('transaction_kind', 'employee_consumption')->firstOrFail();
        $this->assertSame('consumed', $consumption->status);
        $this->assertSame($employee->id, $consumption->employee_id);
        $this->assertSame(0, $consumption->grand_total);
        $this->assertNull($consumption->paid_at);
        $this->assertNull($consumption->payment_method);
        $this->assertSame(500, $consumption->cogs_total);
        $this->assertDatabaseHas('stock_movements', [
            'material_stock_batch_id' => $batch->id,
            'transaction_id' => $consumption->id,
            'type' => 'out',
            'quantity' => 10,
            'cost_amount' => 500,
        ]);
        $consumptionExpense = Expense::query()->where('reference_number', $consumption->invoice)->firstOrFail();
        $this->assertSame('Konsumsi', ExpenseCategory::query()->findOrFail($consumptionExpense->expense_category_id)->name);
        $this->assertTrue($consumptionExpense->is_non_cash);
        $this->assertSame(500, $consumptionExpense->amount);
        $this->assertDatabaseMissing('expense_payments', ['expense_id' => $consumptionExpense->id]);
        $this->actingAs($manager)->get(route('pos.expenses.index'))
            ->assertOk()
            ->assertSee('Non-tunai')
            ->assertSee('Konsumsi non-tunai')
            ->assertSee('tidak mengurangi kas');

        $this->get(route('pos.index', ['module' => 'ringkasan', 'month' => '2028-02']))
            ->assertOk()
            ->assertSee('Rp1.069')
            ->assertSee('29 hari kalender');
    }

    public function test_table_qr_binds_online_order_to_table_without_trusting_submitted_table_ids(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $table = DiningTable::create(['number' => 'A1', 'capacity' => 4]);

        $this->actingAs($user)->get(route('tables.enter', $table->qr_token))
            ->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()->assertSee('Meja A1');

        $this->post(route('orders.store'), [
            'customer_name' => 'Rani',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame($table->id, $transaction->dining_table_id);
        $this->assertSame('dine_in', $transaction->transaction_type);
        $this->assertDatabaseHas('dining_tables', ['id' => $table->id, 'status' => 'occupied']);
        $this->get(route('orders.show', $transaction->invoice))->assertOk()->assertSee('Meja A1');
        $this->actingAs($user)->from(route('pos.index'))
            ->patch(route('pos.tables.clear', $table))
            ->assertRedirect(route('pos.index'))
            ->assertSessionHasErrors('table');
        $this->actingAs($user)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'cash',
            'amount_paid' => 22000,
        ])->assertRedirect();
        $this->actingAs($user)->patch(route('pos.tables.clear', $table))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dining_tables', ['id' => $table->id, 'status' => 'available']);

        $otherTable = DiningTable::create(['number' => 'B2', 'capacity' => 2]);
        $this->post(route('orders.store'), [
            'customer_name' => 'Rani',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'dining_table_id' => $otherTable->id,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('dining_table_id');
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_customer_profiles_are_reused_by_normalized_phone_number(): void
    {
        $menu = $this->menu(22000);
        $this->openShift();

        foreach ([
            ['name' => 'Nadia', 'phone' => '0812 345 67890'],
            ['name' => 'Nadia Baru', 'phone' => '081234567890'],
        ] as $customer) {
            $this->post(route('orders.store'), [
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'transaction_type' => 'takeaway',
                'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(1, Customer::query()->count());
        $profile = Customer::query()->firstOrFail();
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame('Nadia Baru', $profile->name);
        $this->assertSame(2, $profile->transactions()->count());
    }

    public function test_coupon_is_recalculated_from_server_prices_and_payment_uses_discounted_total(): void
    {
        $menu = $this->menu(20000);
        $user = $this->openShift();
        $coupon = Coupon::create(['code' => 'HEMAT25', 'type' => 'percentage', 'value' => 25, 'is_active' => true]);
        $items = [['menu_id' => $menu->id, 'quantity' => 2]];

        $this->postJson(route('coupons.quote'), ['code' => 'hemat25', 'items' => $items])
            ->assertOk()
            ->assertJson(['subtotal' => 40000, 'discount' => 10000, 'grand_total' => 30000]);

        $this->post(route('orders.store'), [
            'customer_name' => 'Sari',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'coupon_code' => 'hemat25',
            'items' => $items,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame($coupon->id, $transaction->coupon_id);
        $this->assertSame(40000, $transaction->subtotal);
        $this->assertSame(10000, $transaction->discount);
        $this->assertSame(30000, $transaction->grand_total);
        $this->assertSame('HEMAT25', $transaction->coupon_code);

        $this->actingAs($user)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'transfer',
            'amount_paid' => 30000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'paid', 'amount_paid' => 30000]);

        $coupon->update(['code' => 'HEMAT25BARU', 'is_active' => false]);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'coupon_code' => 'HEMAT25']);
        $this->post(route('orders.store'), [
            'customer_name' => 'Sari',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'coupon_code' => 'HEMAT25',
            'items' => $items,
        ])->assertSessionHasErrors('coupon_code');
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_coupon_discount_is_capped_at_subtotal_and_admin_routes_require_authentication(): void
    {
        $menu = $this->menu(20000);
        $user = $this->openShift();
        Coupon::create(['code' => 'POTONGAN', 'type' => 'fixed', 'value' => 50000, 'is_active' => true]);

        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 20000,
            'coupon_code' => 'POTONGAN',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', [
            'subtotal' => 20000,
            'discount' => 20000,
            'grand_total' => 0,
        ]);
    }

    public function test_customer_cannot_create_tables_or_coupons_without_cashier_authentication(): void
    {
        $this->post(route('pos.tables.store'), ['number' => 'A1', 'capacity' => 4])->assertRedirect(route('login'));
        $this->post(route('pos.coupons.store'), ['code' => 'HEMAT', 'type' => 'fixed', 'value' => 1000])->assertRedirect(route('login'));
        $this->assertDatabaseCount('dining_tables', 0);
        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_supplier_purchase_receipt_updates_fifo_stock_and_payments_reconcile(): void
    {
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'kg']);
        $coffee = Material::create(['name' => 'Biji kopi', 'unit_id' => $unit->id, 'minimum_quantity' => 1]);
        $milk = Material::create(['name' => 'Susu', 'unit_id' => $unit->id, 'minimum_quantity' => 1]);

        $this->actingAs($user)->post(route('pos.suppliers.store'), [
            'code' => 'spr-kopi',
            'name' => 'Pemasok Kopi',
            'phone' => '08123456789',
            'email' => 'sales@example.test',
            'address' => 'Bandung',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $supplier = Supplier::query()->firstOrFail();
        $this->assertSame('SPR-KOPI', $supplier->code);
        $this->actingAs($user)->get(route('pos.purchases.index'))
            ->assertOk()
            ->assertSee('Pemasok Kopi')
            ->assertSee('Buat pembelian bahan');

        $this->actingAs($user)->post(route('pos.purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => today()->toDateString(),
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'initial_payment' => 2000,
            'payment_method' => 'transfer',
            'notes' => 'Pembelian uji',
            'items' => [
                [
                    'material_id' => $coffee->id,
                    'quantity' => '2.500',
                    'unit_cost' => 4000,
                    'expires_at' => today()->addDays(30)->toDateString(),
                ],
                [
                    'material_id' => $milk->id,
                    'quantity' => '1.000',
                    'unit_cost' => 2000,
                    'expires_at' => '',
                ],
            ],
            'subtotal' => 1,
            'grand_total' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $purchase = Purchase::query()->with(['items', 'payments'])->firstOrFail();
        $this->assertSame('pending', $purchase->status);
        $this->assertSame('partial', $purchase->payment_status);
        $this->assertSame(12000, $purchase->subtotal);
        $this->assertSame(1200, $purchase->discount);
        $this->assertSame(10800, $purchase->grand_total);
        $this->assertSame(2, $purchase->items->count());
        $this->assertSame(2000, $purchase->payments->sum('amount'));
        $this->assertEquals(0, app(RecipeInventory::class)->onHandByMaterial([$coffee->id, $milk->id])->sum());
        $this->actingAs($user)->get(route('pos.purchases.index'))
            ->assertOk()
            ->assertSee($purchase->purchase_code)
            ->assertSee('Konfirmasi pembelian');

        $this->actingAs($user)->patch(route('pos.purchases.confirm', $purchase))->assertRedirect();
        $purchase->refresh();
        $this->assertSame('confirmed', $purchase->status);
        $this->assertEquals(0, app(RecipeInventory::class)->onHandByMaterial([$coffee->id, $milk->id])->sum());

        $this->actingAs($user)->patch(route('pos.purchases.receive', $purchase))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'status' => 'received']);
        $this->assertEquals(3.5, app(RecipeInventory::class)->onHandByMaterial([$coffee->id, $milk->id])->sum());
        $this->assertDatabaseCount('stock_movements', 2);
        $this->actingAs($user)->get(route('pos.purchases.index'))
            ->assertOk()
            ->assertSee('Barang diterima')
            ->assertSee('Catat pembayaran');
        $this->assertDatabaseHas('stock_movements', [
            'description' => 'Penerimaan pembelian '.$purchase->purchase_code,
            'type' => 'in',
        ]);

        $this->actingAs($user)->from(route('pos.purchases.index'))
            ->patch(route('pos.purchases.receive', $purchase))
            ->assertRedirect(route('pos.purchases.index'))
            ->assertSessionHasErrors('purchase');
        $this->assertDatabaseCount('stock_movements', 2);

        $this->actingAs($user)->from(route('pos.purchases.index'))
            ->post(route('pos.purchases.payments.store', $purchase), [
                'paid_at' => today()->toDateString(),
                'amount' => 9000,
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('pos.purchases.index'))
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('pos.purchases.payments.store', $purchase), [
            'paid_at' => today()->toDateString(),
            'amount' => 8800,
            'payment_method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'payment_status' => 'paid']);
        $this->assertSame(10800, $purchase->payments()->sum('amount'));
    }

    public function test_supplier_and_purchase_management_requires_cashier_authentication(): void
    {
        $supplierPayload = ['code' => 'SUP-1', 'name' => 'Pemasok'];
        $this->get(route('pos.purchases.index'))->assertRedirect(route('login'));
        $this->post(route('pos.suppliers.store'), $supplierPayload)->assertRedirect(route('login'));
        $this->post(route('pos.purchases.store'), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('suppliers', 0);
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_purchase_refund_returns_reserve_quantity_and_remove_stock_from_the_source_batch(): void
    {
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'kg']);
        $material = Material::create(['name' => 'Tepung retur', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $supplier = Supplier::create(['code' => 'TEPUNG', 'name' => 'Pemasok Tepung']);
        $purchase = $this->createReceivedPurchase($user, $supplier, $material, '2.500', 4000, 10, 4000);
        $item = $purchase->items()->firstOrFail();

        $this->actingAs($user)->get(route('pos.purchase-returns.index', ['purchase_id' => $purchase->id]))
            ->assertOk()
            ->assertSee($purchase->purchase_code)
            ->assertSee('Pengurangan utang pemasok');

        $payload = [
            'purchase_id' => $purchase->id,
            'return_date' => today()->toDateString(),
            'settlement_method' => 'refund',
            'items' => [[
                'purchase_item_id' => $item->id,
                'quantity' => '1.000',
                'reason' => 'Kemasan rusak',
            ]],
        ];
        $this->actingAs($user)->post(route('pos.purchase-returns.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $return = PurchaseReturn::query()->with('details')->firstOrFail();
        $this->assertSame(3600, $return->amount);
        $this->assertSame(3600, $return->details->firstOrFail()->amount);
        $this->assertSame('pending', $return->status);

        $this->actingAs($user)->from(route('pos.purchase-returns.index'))->post(route('pos.purchase-returns.store'), [
            ...$payload,
            'items' => [[...$payload['items'][0], 'quantity' => '2.000']],
        ])
            ->assertRedirect(route('pos.purchase-returns.index'))
            ->assertSessionHasErrors('items');
        $this->assertDatabaseCount('purchase_returns', 1);

        $this->actingAs($user)->patch(route('pos.purchase-returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $batch = MaterialStockBatch::query()->where('batch_code', 'PB-'.$purchase->purchase_code.'-'.$item->id)->firstOrFail();
        $this->assertDatabaseHas('stock_movements', [
            'material_stock_batch_id' => $batch->id,
            'type' => 'out',
            'quantity' => '1.000',
            'description' => 'Retur pembelian '.$purchase->purchase_code.' ('.$return->return_code.')',
        ]);
        $this->assertEquals(1.5, app(RecipeInventory::class)->onHandByMaterial([$material->id])->get($material->id));
        $this->assertSame(4000, $purchase->payments()->sum('amount'));
        $this->assertDatabaseHas('purchase_returns', ['id' => $return->id, 'status' => 'confirmed']);
    }

    public function test_purchase_replacement_creates_a_new_batch_and_never_lets_return_stock_go_negative(): void
    {
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'liter']);
        $material = Material::create(['name' => 'Susu retur', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $supplier = Supplier::create(['code' => 'SUSU-RETUR', 'name' => 'Pemasok Susu']);
        $purchase = $this->createReceivedPurchase($user, $supplier, $material, '2.000', 5000);
        $item = $purchase->items()->firstOrFail();

        $this->actingAs($user)->post(route('pos.purchase-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => today()->toDateString(),
            'settlement_method' => 'replacement',
            'items' => [[
                'purchase_item_id' => $item->id,
                'quantity' => '1.000',
                'replacement_expires_at' => today()->addDays(90)->toDateString(),
                'reason' => 'Barang rusak',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $return = PurchaseReturn::query()->with('details')->firstOrFail();
        $this->assertSame(0, $return->amount);

        $sourceBatch = MaterialStockBatch::query()->where('batch_code', 'PB-'.$purchase->purchase_code.'-'.$item->id)->firstOrFail();
        $sourceBatch->movements()->create(['type' => 'out', 'quantity' => 1.5, 'description' => 'Stok dipakai sebelum retur']);
        $this->actingAs($user)->from(route('pos.purchase-returns.index'))->patch(route('pos.purchase-returns.confirm', $return))
            ->assertRedirect(route('pos.purchase-returns.index'))
            ->assertSessionHasErrors('items');
        $this->assertDatabaseHas('purchase_returns', ['id' => $return->id, 'status' => 'pending']);
        $this->assertSame(2, DB::table('stock_movements')->count());
        $this->assertDatabaseMissing('material_stock_batches', ['batch_code' => 'PBRT-'.$return->return_code.'-'.$return->details->firstOrFail()->id]);

        DB::table('stock_movements')->where('description', 'Stok dipakai sebelum retur')->delete();
        $this->actingAs($user)->patch(route('pos.purchase-returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $replacementDetail = $return->details->firstOrFail();
        $replacementBatch = MaterialStockBatch::query()
            ->where('batch_code', 'PBRT-'.$return->return_code.'-'.$replacementDetail->id)
            ->firstOrFail();
        $this->assertSame(today()->addDays(90)->toDateString(), $replacementBatch->expires_at->toDateString());
        $this->assertEquals(2.0, app(RecipeInventory::class)->onHandByMaterial([$material->id])->get($material->id));
        $this->assertDatabaseHas('stock_movements', [
            'material_stock_batch_id' => $sourceBatch->id,
            'type' => 'out',
            'quantity' => '1.000',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'material_stock_batch_id' => $replacementBatch->id,
            'type' => 'in',
            'quantity' => '1.000',
        ]);
    }

    public function test_purchase_debt_reduction_reserves_balance_and_reconciles_payments(): void
    {
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'kg']);
        $material = Material::create(['name' => 'Gula retur', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $supplier = Supplier::create(['code' => 'GULA-RETUR', 'name' => 'Pemasok Gula']);
        $purchase = $this->createReceivedPurchase($user, $supplier, $material, '2.000', 5000, initialPayment: 2000);
        $item = $purchase->items()->firstOrFail();

        $this->actingAs($user)->post(route('pos.purchase-returns.store'), [
            'purchase_id' => $purchase->id,
            'return_date' => today()->toDateString(),
            'settlement_method' => 'debt_reduction',
            'items' => [[
                'purchase_item_id' => $item->id,
                'quantity' => '1.000',
                'reason' => 'Kelebihan kirim',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $return = PurchaseReturn::query()->firstOrFail();
        $this->assertSame(5000, $return->amount);

        $this->actingAs($user)->from(route('pos.purchases.index'))->post(route('pos.purchases.payments.store', $purchase), [
            'paid_at' => today()->toDateString(),
            'amount' => 3001,
            'payment_method' => 'transfer',
        ])->assertRedirect(route('pos.purchases.index'))->assertSessionHasErrors('amount');

        $this->actingAs($user)->patch(route('pos.purchase-returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $purchase->refresh();
        $this->assertSame('partial', $purchase->payment_status);
        $this->assertSame(2000, $purchase->payments()->sum('amount'));

        $this->actingAs($user)->post(route('pos.purchases.payments.store', $purchase), [
            'paid_at' => today()->toDateString(),
            'amount' => 3000,
            'payment_method' => 'transfer',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'payment_status' => 'paid']);
        $this->assertSame(5000, $purchase->payments()->sum('amount'));

        auth()->logout();
        $this->get(route('pos.purchase-returns.index'))->assertRedirect(route('login'));
        $this->post(route('pos.purchase-returns.store'), [])->assertRedirect(route('login'));
    }

    public function test_operational_reports_reconcile_sales_cash_purchases_and_recipe_stock(): void
    {
        $user = $this->openShift();
        $menu = $this->menu(22000);
        $unit = Unit::create(['name' => 'kg']);
        $material = Material::create(['name' => 'Kopi laporan', 'unit_id' => $unit->id, 'minimum_quantity' => 1]);
        $supplier = Supplier::create(['code' => 'LAPORAN', 'name' => 'Pemasok Laporan']);
        $purchase = $this->createReceivedPurchase($user, $supplier, $material, '2.000', 1000, initialPayment: 500);
        $expenseCategory = ExpenseCategory::create(['name' => 'Operasional laporan']);
        $expenseSubcategory = ExpenseSubcategory::create([
            'expense_category_id' => $expenseCategory->id,
            'name' => 'Tagihan laporan',
        ]);

        $this->actingAs($user)->post(route('pos.expenses.store'), [
            'expense_date' => today()->toDateString(),
            'expense_category_id' => $expenseCategory->id,
            'expense_subcategory_id' => $expenseSubcategory->id,
            'amount' => 5000,
            'initial_payment' => 700,
            'payment_method' => 'cash',
            'description' => 'Biaya laporan uji',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $sale = Transaction::query()->with('details')->firstOrFail();
        $this->actingAs($user)->post(route('pos.returns.store'), [
            'transaction_id' => $sale->id,
            'return_date' => today()->toDateString(),
            'refund_method' => 'refund',
            'items' => [[
                'transaction_detail_id' => $sale->details->firstOrFail()->id,
                'quantity' => 1,
                'reason' => 'Uji laporan refund',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $salesReturn = TransactionReturn::query()->firstOrFail();
        $this->actingAs($user)->patch(route('pos.returns.confirm', $salesReturn))
            ->assertRedirect()->assertSessionHasNoErrors();

        $period = ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()];
        $this->actingAs($user)->get(route('pos.reports.index', ['report' => 'sales'] + $period))
            ->assertOk()
            ->assertSee($sale->invoice)
            ->assertSee('Penjualan setelah refund')
            ->assertSee('Rp0');
        $salesCsv = $this->actingAs($user)->get(route('pos.reports.export', ['report' => 'sales'] + $period))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();
        $this->assertStringContainsString($sale->invoice, $salesCsv);
        $this->assertStringContainsString('Pelanggan', $salesCsv);
        $this->actingAs($user)->get(route('pos.reports.index', ['report' => 'product-sales'] + $period))
            ->assertOk()
            ->assertSee('Kopi Susu')
            ->assertSee('22.000');
        $productCsv = $this->actingAs($user)->get(route('pos.reports.export', ['report' => 'product-sales'] + $period))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Kopi Susu', $productCsv);
        $this->actingAs($user)->get(route('pos.reports.index', ['report' => 'purchases'] + $period))
            ->assertOk()
            ->assertSee($purchase->purchase_code)
            ->assertSee('Pemasok Laporan');
        $purchaseCsv = $this->actingAs($user)->get(route('pos.reports.export', ['report' => 'purchases'] + $period))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString($purchase->purchase_code, $purchaseCsv);
        $this->actingAs($user)->get(route('pos.reports.index', ['report' => 'cash-flow'] + $period))
            ->assertOk()
            ->assertSee('Penerimaan penjualan')
            ->assertSee('Refund penjualan')
            ->assertSee('Pembayaran pemasok')
            ->assertSee('Pembayaran pengeluaran')
            ->assertSee('Rp22.000');
        $cashFlowCsv = $this->actingAs($user)->get(route('pos.reports.export', ['report' => 'cash-flow'] + $period))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Refund penjualan', $cashFlowCsv);
        $this->actingAs($user)->get(route('pos.reports.index', ['report' => 'stock'] + $period))
            ->assertOk()
            ->assertSee('Kopi laporan')
            ->assertSee('2,000');
        $stockCsv = $this->actingAs($user)->get(route('pos.reports.export', ['report' => 'stock'] + $period))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Kopi laporan', $stockCsv);
        $this->actingAs($user)->get(route('pos.reports.index', [
            'report' => 'stock-card',
            'material_id' => $material->id,
            ...$period,
        ]))->assertOk()->assertSee('Penerimaan pembelian');
        $stockCardCsv = $this->actingAs($user)->get(route('pos.reports.export', [
            'report' => 'stock-card',
            'material_id' => $material->id,
            ...$period,
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Kopi laporan', $stockCardCsv);

        $this->actingAs($user)->from(route('pos.reports.index'))->get(route('pos.reports.index', [
            'report' => 'sales',
            'start_date' => today()->addDay()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertRedirect(route('pos.reports.index'))->assertSessionHasErrors('end_date');
        $this->assertDatabaseHas('cashier_shifts', ['user_id' => $user->id, 'closed_at' => null]);

        auth()->logout();
        $this->get(route('pos.reports.index'))->assertRedirect(route('login'));
        $this->get(route('pos.reports.export'))->assertRedirect(route('login'));
    }

    public function test_cash_flow_uses_online_order_payment_date_instead_of_order_creation_date(): void
    {
        $menu = $this->menu(18000);
        $user = $this->openShift();
        $this->post(route('orders.store'), [
            'customer_name' => 'Pembayaran beda hari',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $transaction = Transaction::query()->firstOrFail();
        $orderDate = $transaction->created_at->toDateString();
        $paymentDate = today()->addDay();

        $this->travelTo($paymentDate->copy()->setTime(10, 0));
        $this->actingAs($user)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'transfer',
            'amount_paid' => 18000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $transaction->refresh();
        $this->assertSame($paymentDate->toDateString(), $transaction->paid_at->toDateString());
        $this->actingAs($user)->get(route('pos.reports.index', [
            'report' => 'cash-flow',
            'start_date' => $paymentDate->toDateString(),
            'end_date' => $paymentDate->toDateString(),
        ]))->assertOk()->assertSee($transaction->invoice)->assertSee('Penerimaan penjualan');
        $this->actingAs($user)->get(route('pos.reports.index', [
            'report' => 'cash-flow',
            'start_date' => $orderDate,
            'end_date' => $orderDate,
        ]))->assertOk()->assertDontSee($transaction->invoice);
    }

    public function test_expense_categories_and_partial_payments_are_validated_and_tracked(): void
    {
        $user = $this->openShift();
        $this->actingAs($user)->post(route('pos.expenses.categories.store'), [
            'name' => 'Operasional',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $category = ExpenseCategory::query()->firstOrFail();
        $this->actingAs($user)->post(route('pos.expenses.subcategories.store'), [
            'expense_category_id' => $category->id,
            'name' => 'Listrik',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $subcategory = ExpenseSubcategory::query()->firstOrFail();

        $this->actingAs($user)->get(route('pos.expenses.index'))
            ->assertOk()
            ->assertSee('Pengeluaran.')
            ->assertSee('Operasional')
            ->assertSee('Listrik');

        $this->actingAs($user)->post(route('pos.expenses.store'), [
            'expense_date' => today()->toDateString(),
            'reference_number' => 'INV-LISTRIK-7',
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'amount' => 100000,
            'initial_payment' => 30000,
            'payment_method' => 'transfer',
            'description' => 'Tagihan listrik bulanan',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $expense = Expense::query()->with('payments')->firstOrFail();
        $this->assertSame('partial', $expense->payment_status);
        $this->assertSame(30000, $expense->payments->sum('amount'));

        $this->actingAs($user)->from(route('pos.expenses.index'))
            ->post(route('pos.expenses.payments.store', $expense), [
                'paid_at' => today()->toDateString(),
                'amount' => 70001,
                'payment_method' => 'cash',
            ])
            ->assertRedirect(route('pos.expenses.index'))
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)->post(route('pos.expenses.payments.store', $expense), [
            'paid_at' => today()->toDateString(),
            'amount' => 70000,
            'payment_method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'payment_status' => 'paid']);
        $this->assertSame(100000, $expense->payments()->sum('amount'));

        $this->actingAs($user)->get(route('pos.expenses.index', [
            'search' => 'INV-LISTRIK',
            'category_id' => $category->id,
            'payment_status' => 'paid',
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
        ]))->assertOk()->assertSee($expense->expense_code)->assertSee('Tagihan listrik bulanan');

        $this->actingAs($user)->get(route('pos.index'))
            ->assertViewHas('summary', fn (array $summary) => $summary['sales_today'] === 0)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->total() === 0);
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($user)->patch(route('pos.expenses.categories.update', $category), [
            'name' => 'Operasional toko',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'expense_category_id' => $category->id]);
    }

    public function test_expense_subcategory_must_belong_to_selected_category_and_management_requires_authentication(): void
    {
        $user = $this->openShift();
        $category = ExpenseCategory::create(['name' => 'Operasional']);
        $otherCategory = ExpenseCategory::create(['name' => 'Perawatan']);
        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $otherCategory->id,
            'name' => 'Perbaikan',
        ]);

        $this->actingAs($user)->from(route('pos.expenses.index'))
            ->post(route('pos.expenses.store'), [
                'expense_date' => today()->toDateString(),
                'expense_category_id' => $category->id,
                'expense_subcategory_id' => $subcategory->id,
                'amount' => 50000,
            ])
            ->assertRedirect(route('pos.expenses.index'))
            ->assertSessionHasErrors('expense_subcategory_id');
        $this->assertDatabaseCount('expenses', 0);

        auth()->logout();
        $this->get(route('pos.expenses.index'))->assertRedirect(route('login'));
        $this->post(route('pos.expenses.categories.store'), ['name' => 'Tidak boleh'])->assertRedirect(route('login'));
        $this->post(route('pos.expenses.store'), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('expense_categories', 2);
    }

    public function test_online_order_is_rejected_if_no_cashier_shift_is_open(): void
    {
        $menu = $this->menu(18000);

        $this->from(route('home'))->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'dine_in',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect(route('home'))->assertSessionHasErrors('items');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_order_form_restores_selected_cart_and_customer_details_after_validation_error(): void
    {
        $menu = $this->menu(22000);
        $this->openShift();

        $this->from(route('home'))->post(route('orders.store'), [
            'customer_name' => '',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertRedirect(route('home'))->assertSessionHasErrors('customer_name');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-initial-cart=', false)
            ->assertSee('quantity&quot;:2', false)
            ->assertSee('value="08123456789"', false);
    }

    public function test_cashier_dashboard_requires_authentication(): void
    {
        $this->get(route('pos.index'))->assertRedirect(route('login'));
        $this->getJson(route('pos.orders.pending-count'))->assertUnauthorized();
        $this->post(route('pos.categories.store'), ['name' => 'Tidak boleh'])
            ->assertRedirect(route('login'));
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_shift_cannot_close_while_online_order_is_unpaid(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $this->post(route('orders.store'), [
            'customer_name' => 'Ayu',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ]);
        $transaction = Transaction::query()->firstOrFail();

        $this->actingAs($user)->from(route('pos.index'))->post(route('pos.shift.close'), ['ending_cash' => 100000])
            ->assertRedirect(route('pos.index'))
            ->assertSessionHasErrors('ending_cash');
        $this->assertDatabaseHas('cashier_shifts', ['user_id' => $user->id, 'closed_at' => null]);

        $this->actingAs($user)->patch(route('pos.orders.cancel', $transaction))->assertRedirect();
        $this->actingAs($user)->post(route('pos.shift.close'), ['ending_cash' => 100000])->assertRedirect();
        $this->assertDatabaseHas('cashier_shifts', ['user_id' => $user->id, 'ending_cash' => 100000]);
    }

    public function test_pos_order_form_restores_selected_cart_after_payment_validation_error(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();

        $this->actingAs($user)->from(route('pos.index'))->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 10000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertRedirect(route('pos.index'))->assertSessionHasErrors('amount_paid');

        $this->actingAs($user)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('quantity&quot;:2', false)
            ->assertSee('value="cash"', false)
            ->assertSee('Kopi Susu');
    }

    public function test_pos_rejects_underpayment_and_records_cash_change(): void
    {
        $menu = $this->menu(25000);
        $user = $this->openShift();
        $payload = [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ];

        $this->actingAs($user)->from(route('pos.index'))->post(route('pos.orders.store'), $payload + ['amount_paid' => 24000])
            ->assertRedirect(route('pos.index'))
            ->assertSessionHasErrors('amount_paid');
        $this->assertDatabaseCount('transactions', 0);

        $response = $this->actingAs($user)->post(route('pos.orders.store'), $payload + ['amount_paid' => 30000]);

        $transaction = Transaction::query()->firstOrFail();
        $response->assertRedirect(route('pos.orders.receipt', $transaction))
            ->assertSessionHas('print_receipt', true);
        $this->assertSame('paid', $transaction->status);
        $this->assertSame(5000, $transaction->change_due);
        $this->get(route('pos.orders.receipt', $transaction))
            ->assertOk()
            ->assertSee($transaction->invoice)
            ->assertSee('Kembalian')
            ->assertSee('window.print()', false);
    }

    public function test_manager_can_add_material_with_initial_stock_and_view_recipe_hpp_margin(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::create(['name' => 'Minuman']);
        $unit = Unit::create(['name' => 'gram']);
        $menu = Menu::create([
            'category_id' => $category->id,
            'name' => 'Kopi Uji HPP',
            'selling_price' => 5000,
            'is_available' => true,
        ]);

        $this->actingAs($manager)->post(route('pos.materials.store'), [
            'name' => 'Kopi bubuk',
            'unit_id' => $unit->id,
            'minimum_quantity' => 0,
            'initial_quantity' => 4,
            'initial_total_cost' => 1000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $material = Material::query()->firstOrFail();
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 0.5]);
        $this->assertDatabaseHas('material_stock_batches', [
            'material_id' => $material->id,
            'unit_cost' => '250.000000',
        ]);

        $this->actingAs($manager)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Estimasi HPP')
            ->assertSee('Rp125')
            ->assertSee('97,5%')
            ->assertSee('HPP / satuan')
            ->assertSee('recipe-table', false)
            ->assertSee('HPP / bahan')
            ->assertSee('data-sidebar-toggle', false);
    }

    public function test_material_supplier_is_optional_and_manager_can_update_it(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $unit = Unit::create(['name' => 'liter']);
        $supplier = Supplier::create(['code' => 'SUSU-1', 'name' => 'Pemasok Susu']);
        $replacementSupplier = Supplier::create(['code' => 'SUSU-2', 'name' => 'Pemasok Alternatif']);

        $this->actingAs($manager)->post(route('pos.materials.store'), [
            'name' => 'Susu segar',
            'unit_id' => $unit->id,
            'supplier_id' => $supplier->id,
            'minimum_quantity' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $material = Material::query()->firstOrFail();
        $this->assertSame($supplier->id, $material->supplier_id);

        $this->actingAs($manager)->patch(route('pos.materials.update', $material), [
            'name' => 'Susu segar',
            'unit_id' => $unit->id,
            'supplier_id' => $replacementSupplier->id,
            'minimum_quantity' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'supplier_id' => $replacementSupplier->id,
            'minimum_quantity' => '1.000',
        ]);
        $this->actingAs($manager)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Supplier (opsional)')
            ->assertSee('Pemasok Alternatif');
    }

    public function test_monthly_summary_counts_paid_sales_and_actual_expense_payments_only(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create(['user_id' => $manager->id, 'starting_cash' => 0, 'opened_at' => now()]);
        $menu = $this->menu(22000);

        $this->actingAs($manager)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $category = ExpenseCategory::create(['name' => 'Biaya pengujian']);
        $subcategory = ExpenseSubcategory::create([
            'expense_category_id' => $category->id,
            'name' => 'Pembayaran',
        ]);
        $expense = Expense::create([
            'expense_code' => 'EXP-MONTHLY-TEST',
            'expense_date' => today(),
            'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id,
            'created_by' => $manager->id,
            'amount' => 20000,
            'payment_status' => 'partial',
            'description' => 'Pembayaran sebagian',
        ]);
        DB::table('expense_payments')->insert([
            [
                'expense_id' => $expense->id,
                'paid_at' => today()->toDateString(),
                'amount' => 12500,
                'payment_method' => 'cash',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'expense_id' => $expense->id,
                'paid_at' => today()->startOfMonth()->subDay()->toDateString(),
                'amount' => 3000,
                'payment_method' => 'cash',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($manager)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Pemasukan bulan ini')
            ->assertSee('Rp22.000')
            ->assertSee('Pengeluaran dibayar bulan ini')
            ->assertSee('Rp12.500')
            ->assertSee('Belum dapat dihitung')
            ->assertSee('10 menu terlaris')
            ->assertSee('10 menu paling sedikit terjual')
            ->assertSee('data-summary-chart', false)
            ->assertSee('Ekspor Excel')
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee('Buka kasir');

        $excel = $this->actingAs($manager)->get(route('pos.summary.export', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8')
            ->streamedContent();
        $this->assertStringContainsString('<?mso-application progid="Excel.Sheet"?>', $excel);
        $this->assertStringContainsString('DETAIL PENJUALAN', $excel);
        $this->assertStringContainsString('PENGELUARAN OPERASIONAL', $excel);
        $this->assertStringContainsString('Kopi Susu', $excel);
        $this->actingAs($manager)->get(route('pos.summary.pdf', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee('Ringkasan Penjualan & Operasional')
            ->assertSee('pencapaian target per hari')
            ->assertSee('Kopi Susu');
    }

    public function test_cashier_without_a_shift_has_a_visible_open_cashier_action(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Buka kasir')
            ->assertSee('Buka shift')
            ->assertDontSee('Buat transaksi')
            ->assertDontSee('data-cart-storage-key="pos-menu-cart"', false)
            ->assertDontSee('Proses transaksi')
            ->assertSee('Ringkasan');

        $this->post(route('pos.shift.open'), ['starting_cash' => 50000, 'shift_number' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cashier_shifts', ['user_id' => $cashier->id, 'starting_cash' => 50000, 'closed_at' => null]);

        $menu = $this->menu(5000);
        $this->get(route('pos.index'))->assertOk()
            ->assertSee('Buat transaksi')
            ->assertSee('data-cart-storage-key="pos-menu-cart"', false)
            ->assertSee('Proses transaksi')
            ->assertSee($menu->name);
        $this->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 5000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', ['customer_name' => 'Pelanggan kasir', 'grand_total' => 5000, 'status' => 'paid']);
    }

    public function test_receipt_requires_staff_authentication_and_paid_transaction(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ]);
        $transaction = Transaction::query()->firstOrFail();

        auth()->logout();
        $this->get(route('pos.orders.receipt', $transaction))->assertRedirect(route('login'));
    }

    public function test_kitchen_cannot_skip_from_pending_to_finished(): void
    {
        $menu = $this->menu(21000);
        $user = $this->openShift();
        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'dine_in',
            'payment_method' => 'transfer',
            'amount_paid' => 21000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ]);
        $transaction = Transaction::query()->firstOrFail();

        $this->actingAs($user)->patch(route('pos.orders.kitchen', $transaction), ['kitchen_status' => 'success'])
            ->assertStatus(422);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'kitchen_status' => 'pending']);
    }

    public function test_customer_payment_is_validated_and_kitchen_stages_advance_in_order(): void
    {
        $menu = $this->menu(23000);
        $user = $this->openShift();
        $this->post(route('orders.store'), [
            'customer_name' => 'Rani',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ]);
        $transaction = Transaction::query()->firstOrFail();

        $this->actingAs($user)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'transfer',
            'amount_paid' => 24000,
        ])->assertStatus(422);
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'pending']);

        $this->actingAs($user)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'cash',
            'amount_paid' => 25000,
        ])->assertRedirect();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'paid', 'change_due' => 2000]);

        $this->patch(route('pos.orders.kitchen', $transaction), ['kitchen_status' => 'onprogress'])->assertRedirect();
        $this->patch(route('pos.orders.kitchen', $transaction), ['kitchen_status' => 'success'])->assertRedirect();
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'kitchen_status' => 'success']);
    }

    public function test_cashier_dashboard_displays_shift_catalog_and_transactions(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();

        $this->actingAs($user)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Buat transaksi')
            ->assertSee($menu->name)
            ->assertSee('Shift kasir sedang berjalan')
            ->assertSee('data-dashboard-toggle', false)
            ->assertDontSee('data-module-dashboard', false)
            ->assertDontSee('Dashboard modul')
            ->assertSee('data-pending-order-badge', false)
            ->assertSee('Proses transaksi')
            ->assertSee('data-cash-tender="exact"', false)
            ->assertSee('data-payment-choice="cash"', false)
            ->assertSee('data-change-amount', false);
    }

    public function test_cashier_can_filter_orders_and_edit_menu_prices(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ]);
        $transaction = Transaction::query()->firstOrFail();

        $this->actingAs($user)->get(route('pos.index', [
            'search' => $transaction->invoice,
            'status' => 'paid',
            'kitchen_status' => 'pending',
        ]))->assertOk()->assertSee($transaction->invoice);

        $this->actingAs($user)->patch(route('pos.menus.update', $menu), [
            'category_id' => $menu->category_id,
            'name' => 'Kopi Susu Baru',
            'description' => 'Resep baru.',
            'selling_price' => 26000,
        ])->assertRedirect();

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'name' => 'Kopi Susu Baru', 'selling_price' => 26000]);
        $this->assertDatabaseHas('transaction_details', ['transaction_id' => $transaction->id, 'item_name' => 'Kopi Susu', 'price' => 22000]);
    }

    public function test_cashier_date_filter_rejects_reversed_range(): void
    {
        $user = $this->openShift();
        $this->actingAs($user)
            ->get(route('pos.index', ['date_from' => '2026-11-03', 'date_to' => '2026-11-02']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_paid_order_consumes_recipe_stock_from_earliest_expiring_batches(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'ml']);
        $material = Material::create(['name' => 'Susu', 'unit_id' => $unit->id, 'minimum_quantity' => 15]);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 20]);
        $expiredBatch = MaterialStockBatch::create([
            'material_id' => $material->id,
            'batch_code' => 'SUSU-EXPIRED',
            'expires_at' => today()->subDay(),
        ]);
        $expiredBatch->movements()->create([
            'type' => 'in',
            'quantity' => 100,
            'description' => 'Stok kedaluwarsa',
        ]);

        $this->actingAs($user)->post(route('pos.materials.stock', $material), [
            'batch_code' => 'SUSU-EXP-1',
            'quantity' => 30,
            'expires_at' => now()->addDays(3)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('pos.materials.stock', $material), [
            'batch_code' => 'SUSU-EXP-2',
            'quantity' => 100,
            'expires_at' => now()->addDays(10)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('orders.store'), [
            'customer_name' => 'Rani',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $transaction = Transaction::query()->firstOrFail();
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('transaction_recipe_details', [
            'transaction_detail_id' => $transaction->details()->firstOrFail()->id,
            'material_id' => $material->id,
            'quantity' => 40,
        ]);
        MenuRecipe::query()->where('menu_id', $menu->id)->update(['quantity' => 50]);

        $this->actingAs($user)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'cash',
            'amount_paid' => 44000,
        ])->assertRedirect();

        $firstBatch = MaterialStockBatch::query()->where('batch_code', 'SUSU-EXP-1')->firstOrFail();
        $secondBatch = MaterialStockBatch::query()->where('batch_code', 'SUSU-EXP-2')->firstOrFail();
        $this->assertDatabaseHas('stock_movements', [
            'material_stock_batch_id' => $firstBatch->id,
            'transaction_id' => $transaction->id,
            'type' => 'out',
            'quantity' => 30,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'material_stock_batch_id' => $secondBatch->id,
            'transaction_id' => $transaction->id,
            'type' => 'out',
            'quantity' => 10,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'material_stock_batch_id' => $expiredBatch->id,
            'transaction_id' => $transaction->id,
            'type' => 'out',
        ]);
        $this->assertSame(1, app(RecipeInventory::class)->availableServings(Menu::query()->whereKey($menu->id)->get())->get($menu->id));
    }

    public function test_payment_is_rejected_without_enough_recipe_stock(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create(['name' => 'Kopi', 'unit_id' => $unit->id, 'minimum_quantity' => 5]);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 15]);
        $batch = MaterialStockBatch::create([
            'material_id' => $material->id,
            'batch_code' => 'KOPI-SEDIKIT',
        ]);
        $batch->movements()->create(['type' => 'in', 'quantity' => 20, 'description' => 'Stok masuk']);
        $this->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $transaction = Transaction::query()->firstOrFail();
        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect();

        $this->actingAs($user)->from(route('pos.index'))->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'cash',
            'amount_paid' => 22000,
        ])->assertRedirect(route('pos.index'))->assertSessionHasErrors('items');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'status' => 'pending']);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_recipe_menu_without_stock_can_be_added_but_cannot_be_submitted_as_an_order(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'ml']);
        $material = Material::create(['name' => 'Susu segar', 'unit_id' => $unit->id, 'minimum_quantity' => 5]);
        $this->actingAs($user)->post(route('pos.menus.recipes.store', $menu), [
            'material_id' => $material->id,
            'quantity' => 20,
        ])->assertRedirect(route('pos.index', ['module' => 'menu']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('open_menu', $menu->id);

        $this->actingAs($user)->get(route('pos.index', ['module' => 'menu']))
            ->assertOk()
            ->assertSee('data-open-after-submit', false)
            ->assertSee('Tambah bahan resep');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-add-menu', false)
            ->assertDontSee('Stok habis');
        $this->actingAs($user)->get(route('pos.index'))
            ->assertOk()
            ->assertSee('data-add-menu', false)
            ->assertDontSee('Stok bahan habis');

        $this->post(route('orders.store'), [
            'customer_name' => 'Nia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_recipe_quantity_is_capped_to_fit_a_maximum_size_order_snapshot(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create(['name' => 'Kopi', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);

        $this->actingAs($user)->post(route('pos.menus.recipes.store', $menu), [
            'material_id' => $material->id,
            'quantity' => '49999999.999',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('pos.menus.recipes.store', $menu), [
            'material_id' => $material->id,
            'quantity' => '50000000',
        ])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('menu_recipes', 1);
    }

    public function test_sales_refund_returns_are_capped_by_paid_quantity_and_discounted_total(): void
    {
        $menu = $this->menu(20000);
        $secondMenu = Menu::create([
            'category_id' => $menu->category_id,
            'name' => 'Teh retur',
            'description' => 'Teh hangat.',
            'selling_price' => 19999,
            'is_available' => true,
        ]);
        $user = $this->openShift();
        $coupon = Coupon::create([
            'code' => 'HEMAT20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
        ]);
        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'coupon_code' => $coupon->code,
            'payment_method' => 'cash',
            'amount_paid' => 32000,
            'items' => [
                ['menu_id' => $menu->id, 'quantity' => 1],
                ['menu_id' => $secondMenu->id, 'quantity' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $sale = Transaction::query()->with('details')->firstOrFail();
        $detail = $sale->details->firstOrFail();
        $this->actingAs($user)->get(route('pos.returns.index', ['transaction_id' => $sale->id]))
            ->assertOk()
            ->assertSee($sale->invoice)
            ->assertSee('Klaim penggantian non-tunai');

        $payload = [
            'transaction_id' => $sale->id,
            'return_date' => today()->toDateString(),
            'refund_method' => 'refund',
            'items' => [[
                'transaction_detail_id' => $detail->id,
                'quantity' => 1,
                'reason' => 'Pesanan tidak sesuai',
            ]],
        ];
        $this->actingAs($user)->post(route('pos.returns.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $return = TransactionReturn::query()->with('details')->firstOrFail();
        $this->assertSame(16000, $return->refund_amount);
        $this->assertSame(16000, $return->details->sum('refund_amount'));

        $this->actingAs($user)->from(route('pos.returns.index'))->post(route('pos.returns.store'), [
            ...$payload,
            'items' => [[...$payload['items'][0], 'quantity' => 1]],
        ])->assertRedirect(route('pos.returns.index'))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('transaction_returns', 1);

        $this->actingAs($user)->patch(route('pos.returns.cancel', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $payload['items'][] = [
            'transaction_detail_id' => $sale->details->last()->id,
            'quantity' => 1,
            'reason' => 'Minuman tidak sesuai',
        ];
        $this->actingAs($user)->post(route('pos.returns.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $return = TransactionReturn::query()->where('status', 'pending')->firstOrFail();
        $this->actingAs($user)->patch(route('pos.returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transaction_returns', [
            'id' => $return->id,
            'status' => 'confirmed',
            'refund_amount' => 32000,
        ]);

        $this->actingAs($user)->from(route('pos.returns.index'))->post(route('pos.returns.store'), [
            ...$payload,
            'items' => [[...$payload['items'][0], 'quantity' => 1]],
        ])->assertRedirect(route('pos.returns.index'))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('transaction_returns', 2);
    }

    public function test_replacement_return_is_a_non_cash_claim_without_dapur_or_stock_mutation(): void
    {
        $menu = $this->menu(22000);
        $user = $this->openShift();
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create(['name' => 'Kopi retur', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 20]);
        $this->actingAs($user)->post(route('pos.materials.stock', $material), [
            'batch_code' => 'KOPI-RETUR-1',
            'quantity' => 100,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $sale = Transaction::query()->with('details')->firstOrFail();
        $movementCount = DB::table('stock_movements')->count();

        $this->actingAs($user)->post(route('pos.returns.store'), [
            'transaction_id' => $sale->id,
            'return_date' => today()->toDateString(),
            'refund_method' => 'replacement',
            'items' => [[
                'transaction_detail_id' => $sale->details->firstOrFail()->id,
                'quantity' => 1,
                'reason' => 'Minuman tumpah saat diserahkan',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $return = TransactionReturn::query()->firstOrFail();
        $this->assertSame(0, $return->refund_amount);
        $this->actingAs($user)->patch(route('pos.returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($movementCount, DB::table('stock_movements')->count());
        $this->assertDatabaseHas('transactions', ['id' => $sale->id, 'kitchen_status' => 'pending']);
        $this->assertDatabaseHas('transaction_returns', ['id' => $return->id, 'status' => 'confirmed']);

        auth()->logout();
        $this->get(route('pos.returns.index'))->assertRedirect(route('login'));
        $this->post(route('pos.returns.store'), [])->assertRedirect(route('login'));
    }

    private function menu(int $price): Menu
    {
        $category = Category::create(['name' => 'Kopi']);

        return Menu::create([
            'category_id' => $category->id,
            'name' => 'Kopi Susu',
            'description' => 'Kopi susu hangat.',
            'selling_price' => $price,
            'is_available' => true,
        ]);
    }

    private function createReceivedPurchase(
        User $user,
        Supplier $supplier,
        Material $material,
        string $quantity,
        int $unitCost,
        int $discountPercent = 0,
        int $initialPayment = 0,
    ): Purchase {
        $payload = [
            'supplier_id' => $supplier->id,
            'purchase_date' => today()->toDateString(),
            'discount_type' => $discountPercent > 0 ? 'percentage' : '',
            'discount_value' => $discountPercent,
            'initial_payment' => $initialPayment,
            'payment_method' => $initialPayment > 0 ? 'cash' : '',
            'items' => [[
                'material_id' => $material->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'expires_at' => '',
            ]],
        ];
        $this->actingAs($user)->post(route('pos.purchases.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $purchase = Purchase::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->patch(route('pos.purchases.confirm', $purchase))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->patch(route('pos.purchases.receive', $purchase))->assertRedirect()->assertSessionHasNoErrors();

        return $purchase->load(['items', 'payments']);
    }

    private function openShift(): User
    {
        $user = User::factory()->create();
        CashierShift::create([
            'user_id' => $user->id,
            'starting_cash' => 100000,
            'opened_at' => now(),
        ]);

        return $user;
    }
}
