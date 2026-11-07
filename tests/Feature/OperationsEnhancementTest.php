<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Material;
use App\Models\Menu;
use App\Models\MenuRecipe;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_orders_completed_sales_and_kitchen_ready_events_appear_in_staff_notification_bell(): void
    {
        $manager = $this->managerWithShift();
        $menu = $this->menu('Kopi Notifikasi');
        $order = $this->placeOnlineOrder($menu);

        $this->actingAs($manager)->getJson(route('pos.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Pesanan online masuk');

        $this->markPaid($manager, $order);
        $this->actingAs($manager)->patch(route('pos.orders.kitchen', $order), ['kitchen_status' => 'onprogress'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->patch(route('pos.orders.kitchen', $order), ['kitchen_status' => 'success'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->getJson(route('pos.notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 3)
            ->assertJsonFragment(['title' => 'Pesanan siap disajikan']);

        $notificationId = $manager->notifications()->latest()->firstOrFail()->id;
        $otherCashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($otherCashier)->patchJson(route('pos.notifications.read', $notificationId))->assertNotFound();
        $this->actingAs($manager)->patchJson(route('pos.notifications.read', $notificationId))
            ->assertOk()
            ->assertJsonPath('unread_count', 2);
        $this->actingAs($manager)->patchJson(route('pos.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_shift_closing_records_expected_cash_difference_and_requires_a_reason(): void
    {
        $manager = $this->managerWithShift(50000);
        $menu = $this->menu('Kopi Kas');
        $sale = $this->placeOnlineOrder($menu);
        $this->markPaid($manager, $sale);
        $this->actingAs($manager)->post(route('pos.shift.close'), ['ending_cash' => 59000])
            ->assertSessionHasErrors('closing_note');
        $this->assertNull(CashierShift::query()->firstOrFail()->fresh()->closed_at);

        $this->actingAs($manager)->post(route('pos.shift.close'), [
            'ending_cash' => 59000,
            'closing_note' => 'Uang kembalian kurang.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $shift = CashierShift::query()->firstOrFail()->fresh();
        $this->assertSame(60000, $shift->expected_cash);
        $this->assertSame(-1000, $shift->cash_difference);
        $this->assertSame('Uang kembalian kurang.', $shift->closing_note);
    }

    public function test_manager_dashboard_and_purchase_recommendations_use_recent_recipe_consumption_without_creating_a_purchase(): void
    {
        $manager = $this->managerWithShift();
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create([
            'name' => 'Biji kopi rekomendasi',
            'unit_id' => $unit->id,
            'minimum_quantity' => 5,
        ]);
        app(RecipeInventory::class)->receive($material->id, 'RECENT-USE', 6000, null, 'Stok awal', '100.000000');
        $menu = $this->menu('Kopi dengan resep');
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 2]);
        $sale = $this->placeOnlineOrder($menu, 2);
        $this->markPaid($manager, $sale);

        $this->actingAs($manager)->get(route('pos.index', ['module' => 'ringkasan']))
            ->assertOk()
            ->assertSee('Fokus operasional')
            ->assertSee('Biji kopi rekomendasi');
        $this->actingAs($manager)->get(route('pos.index', ['module' => 'stok']))
            ->assertOk()
            ->assertSee('Saran belanja bahan')
            ->assertSee('Pemakaian 30 hari')
            ->assertSee('3,000');
        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_direct_pos_sale_notifies_staff_after_the_transaction_is_saved(): void
    {
        $manager = $this->managerWithShift();
        $menu = $this->menu('Kopi POS');

        $this->actingAs($manager)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'amount_paid' => $menu->selling_price,
        ])->assertRedirect();

        $notification = $manager->notifications()->latest()->firstOrFail();
        $this->assertSame('Transaksi selesai', $notification->data['title']);
        $this->assertStringContainsString('/kasir/transaksi/', $notification->data['url']);
    }

    public function test_menu_margin_report_uses_transaction_recipe_and_batch_cost_snapshots(): void
    {
        $manager = $this->managerWithShift();
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create([
            'name' => 'Biji kopi margin',
            'unit_id' => $unit->id,
            'minimum_quantity' => 0,
        ]);
        app(RecipeInventory::class)->receive($material->id, 'MARGIN-COST', 10000, null, 'Stok masuk', '1000.000000');
        $menu = $this->menu('Latte Margin', 10000);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 2]);
        $sale = $this->placeOnlineOrder($menu);
        $this->markPaid($manager, $sale);

        $this->actingAs($manager)->get(route('pos.reports.index', [
            'report' => 'menu-margin',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()
            ->assertSee('Margin per menu')
            ->assertSee('Latte Margin')
            ->assertSee('Rp2.000')
            ->assertSee('Rp8.000')
            ->assertSee('80,0%');

        $csv = $this->actingAs($manager)->get(route('pos.reports.export', [
            'report' => 'menu-margin',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Estimasi HPP bahan', $csv);
        $this->assertStringContainsString('Latte Margin', $csv);
    }

    public function test_purchase_recommendation_subtracts_confirmed_incoming_purchase_quantity(): void
    {
        $manager = $this->managerWithShift();
        $unit = Unit::create(['name' => 'gram']);
        $supplier = Supplier::create(['code' => 'SUP-REC', 'name' => 'Pemasok Rekomendasi']);
        $material = Material::create([
            'name' => 'Biji kopi incoming',
            'unit_id' => $unit->id,
            'supplier_id' => $supplier->id,
            'minimum_quantity' => 5,
        ]);
        app(RecipeInventory::class)->receive($material->id, 'INCOMING-USE', 6000, null, 'Stok awal', '100.000000');
        $menu = $this->menu('Kopi pesanan berjalan');
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 2]);
        $sale = $this->placeOnlineOrder($menu, 2);
        $this->markPaid($manager, $sale);
        $purchase = Purchase::create([
            'purchase_code' => 'BELI-REC-001',
            'supplier_id' => $supplier->id,
            'created_by' => $manager->id,
            'purchase_date' => today(),
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
            'subtotal' => 100,
            'discount' => 0,
            'grand_total' => 100,
        ]);
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'item_type' => 'material',
            'material_id' => $material->id,
            'material_name' => $material->name,
            'unit_name' => $unit->name,
            'quantity' => 1,
            'unit_cost' => 100,
            'line_total' => 100,
        ]);

        $this->actingAs($manager)->get(route('pos.index', ['module' => 'stok']))
            ->assertOk()
            ->assertSee('2,000')
            ->assertSee('1,000');
        $this->assertSame(1, DB::table('purchase_items')->where('purchase_id', $purchase->id)->count());
    }

    private function managerWithShift(int $startingCash = 0): User
    {
        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create([
            'user_id' => $manager->id,
            'starting_cash' => $startingCash,
            'opened_at' => now(),
        ]);

        return $manager;
    }

    private function menu(string $name, int $price = 10000): Menu
    {
        $category = Category::firstOrCreate(['name' => 'Minuman']);

        return Menu::create([
            'category_id' => $category->id,
            'name' => $name,
            'selling_price' => $price,
            'is_available' => true,
        ]);
    }

    private function placeOnlineOrder(Menu $menu, int $quantity = 1): Transaction
    {
        $this->post(route('orders.store'), [
            'customer_name' => 'Pelanggan Uji',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => $quantity]],
        ])->assertRedirect();

        return Transaction::query()->latest('id')->firstOrFail();
    }

    private function markPaid(User $manager, Transaction $transaction): void
    {
        $this->actingAs($manager)->patch(route('pos.orders.paid', $transaction), [
            'payment_method' => 'cash',
            'amount_paid' => $transaction->grand_total,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
}
