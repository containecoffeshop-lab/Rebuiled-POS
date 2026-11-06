<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\ProductPromotion;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_best_product_promotion_applies_and_coupon_is_calculated_after_it(): void
    {
        $menu = $this->menu('Kopi Susu', 10000);
        $customer = Customer::create(['name' => 'Nadia', 'phone' => '08123456789']);
        $promotion = ProductPromotion::create([
            'name' => 'Hemat 10%',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_quantity' => 2,
            'all_menus' => false,
            'all_customers' => false,
            'is_active' => true,
        ]);
        $promotion->menus()->attach($menu);
        $promotion->customers()->attach($customer);
        Coupon::create(['code' => 'HEMAT5', 'type' => 'percentage', 'value' => 5, 'is_active' => true]);
        $cashier = $this->openShift();

        $this->postJson(route('coupons.quote'), [
            'code' => 'HEMAT5',
            'customer_phone' => '0812-3456-789',
            'items' => [['menu_id' => $menu->id, 'quantity' => 3]],
        ])->assertOk()->assertJson([
            'subtotal' => 30000,
            'promotion_discount' => 3000,
            'coupon_discount' => 1350,
            'discount' => 4350,
            'grand_total' => 25650,
        ]);

        $this->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '0812-3456-789',
            'transaction_type' => 'takeaway',
            'coupon_code' => 'HEMAT5',
            'items' => [['menu_id' => $menu->id, 'quantity' => 3]],
        ])->assertRedirect();

        $sale = Transaction::query()->with('details')->firstOrFail();
        $this->assertSame(30000, $sale->subtotal);
        $this->assertSame(3000, $sale->promotion_discount);
        $this->assertSame(1350, $sale->coupon_discount);
        $this->assertSame(4350, $sale->discount);
        $this->assertSame(25650, $sale->grand_total);
        $this->assertSame('Hemat 10%', $sale->details->first()->promotion_name);
        $this->assertSame(3000, $sale->details->first()->promotion_discount);

        $this->actingAs($cashier)->patch(route('pos.orders.paid', $sale), [
            'payment_method' => 'cash',
            'amount_paid' => $sale->grand_total,
        ])->assertRedirect();
        $this->actingAs($cashier)->post(route('pos.returns.store'), [
            'transaction_id' => $sale->id,
            'return_date' => today()->toDateString(),
            'refund_method' => 'refund',
            'items' => [[
                'transaction_detail_id' => $sale->details->first()->id,
                'quantity' => 3,
                'reason' => 'Pembatalan penuh',
            ]],
        ])->assertRedirect();
        $this->assertSame($sale->grand_total, TransactionReturn::query()->firstOrFail()->refund_amount);
    }

    public function test_customer_target_and_minimum_quantity_are_enforced(): void
    {
        $menu = $this->menu('Americano', 20000);
        $customer = Customer::create(['name' => 'Rani', 'phone' => '08122223333']);
        $promotion = ProductPromotion::create([
            'name' => 'Pelanggan pilihan',
            'discount_type' => 'fixed',
            'discount_value' => 5000,
            'minimum_quantity' => 2,
            'all_menus' => true,
            'all_customers' => false,
            'is_active' => true,
        ]);
        $promotion->customers()->attach($customer);
        $this->openShift();

        $this->postJson(route('coupons.quote'), [
            'customer_phone' => $customer->phone,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertOk()->assertJson(['promotion_discount' => 0, 'grand_total' => 20000]);

        $this->postJson(route('coupons.quote'), [
            'customer_phone' => '08199998888',
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertOk()->assertJson(['promotion_discount' => 0, 'grand_total' => 40000]);

        $this->postJson(route('coupons.quote'), [
            'customer_phone' => $customer->phone,
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertOk()->assertJson(['promotion_discount' => 10000, 'grand_total' => 30000]);
    }

    public function test_manager_can_create_and_disable_a_menu_promotion(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $menu = $this->menu('Cokelat', 25000);
        $this->actingAs($manager)->get(route('pos.promotions.index'))->assertOk()->assertSee('Promo menu');

        $this->post(route('pos.promotions.store'), [
            'name' => 'Diskon pembukaan',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'minimum_quantity' => 1,
            'all_menus' => 0,
            'menu_ids' => [$menu->id],
            'all_customers' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $promotion = ProductPromotion::query()->firstOrFail();
        $this->assertFalse($promotion->all_menus);
        $this->assertTrue($promotion->all_customers);
        $this->assertTrue($promotion->menus->contains($menu));
        $this->patch(route('pos.promotions.toggle', $promotion))->assertRedirect();
        $this->assertDatabaseHas('product_promotions', ['id' => $promotion->id, 'is_active' => false]);
    }

    public function test_cashier_cannot_manage_promotions(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('pos.promotions.index'))->assertForbidden();
        $this->post(route('pos.promotions.store'), [])->assertForbidden();
    }

    private function menu(string $name, int $price): Menu
    {
        $category = Category::create(['name' => 'Kopi']);

        return Menu::create([
            'category_id' => $category->id,
            'name' => $name,
            'selling_price' => $price,
            'is_available' => true,
        ]);
    }

    private function openShift(): User
    {
        $user = User::factory()->create(['role' => 'manager']);
        CashierShift::create([
            'user_id' => $user->id,
            'starting_cash' => 100000,
            'opened_at' => now(),
        ]);

        return $user;
    }
}
