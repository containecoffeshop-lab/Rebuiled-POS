<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionChargesTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_configuration_is_optional_and_applies_to_pos_and_customer_orders_as_a_snapshot(): void
    {
        [$manager, $menu] = $this->setupStore();
        $this->actingAs($manager);

        $this->postJson(route('coupons.quote'), [
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('grand_total', 10000)
            ->assertJsonPath('charges', []);

        Coupon::create(['code' => 'HEMAT', 'type' => 'fixed', 'value' => 1000, 'is_active' => true]);
        $this->put(route('pos.settings.charges.update'), [
            'charges' => [
                ['code' => 'PJK', 'name' => 'Pajak', 'value' => '10%', 'is_active' => 1],
                ['code' => 'OPR', 'name' => 'Biaya layanan', 'value' => '2000', 'is_active' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->postJson(route('coupons.quote'), [
            'code' => 'HEMAT',
            'customer_phone' => '08123456789',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('subtotal', 10000)
            ->assertJsonPath('discount', 1000)
            ->assertJsonPath('charges.0.amount', 900)
            ->assertJsonPath('charges.1.amount', 2000)
            ->assertJsonPath('grand_total', 11900);

        $this->post(route('orders.store'), [
            'customer_name' => 'Nadia',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'coupon_code' => 'HEMAT',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $onlineOrder = Transaction::query()->with('charges')->firstOrFail();
        $this->assertSame(11900, $onlineOrder->grand_total);
        $this->assertEqualsCanonicalizing(['PJK', 'OPR'], $onlineOrder->charges->pluck('code')->all());
        $this->assertDatabaseHas('transaction_charges', [
            'transaction_id' => $onlineOrder->id,
            'code' => 'PJK',
            'rate' => '10%',
            'amount' => 900,
        ]);
        $this->get(route('orders.show', $onlineOrder->invoice))
            ->assertOk()
            ->assertSee('Pajak (PJK)')
            ->assertSee('Biaya layanan (OPR)')
            ->assertSee('11.900');

        $this->put(route('pos.settings.charges.update'), [
            'charges' => [
                ['code' => 'PJK', 'name' => 'Pajak baru', 'value' => '20%', 'is_active' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(11900, $onlineOrder->fresh()->grand_total);
        $this->assertSame([900, 2000], $onlineOrder->fresh()->charges->pluck('amount')->all());

        $this->post(route('pos.orders.store'), [
            'customer_name' => 'Pelanggan POS',
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 12000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $posSale = Transaction::query()->where('status', 'paid')->firstOrFail();
        $this->assertSame(12000, $posSale->grand_total);
        $this->assertDatabaseHas('transaction_charges', [
            'transaction_id' => $posSale->id,
            'code' => 'PJK',
            'amount' => 2000,
        ]);
    }

    public function test_only_managers_can_configure_charges_and_percentages_are_bounded(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->put(route('pos.settings.charges.update'), [
            'charges' => [['code' => 'PJK', 'name' => 'Pajak', 'value' => '10%', 'is_active' => 1]],
        ])->assertForbidden();
        $this->assertDatabaseCount('store_charges', 0);

        $this->actingAs($manager)->put(route('pos.settings.charges.update'), [
            'charges' => [['code' => 'PJK', 'name' => 'Pajak', 'value' => '100.01%', 'is_active' => 1]],
        ])->assertSessionHasErrors('charges.0.value');
        $this->assertDatabaseCount('store_charges', 0);

        $this->put(route('pos.settings.charges.update'), [
            'charges' => [['code' => 'PJK', 'name' => 'Pajak', 'value' => '10.5%', 'is_active' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('store_charges', ['code' => 'PJK', 'value' => '10.5%', 'is_active' => false]);
    }

    /**
     * @return array{User, Menu}
     */
    private function setupStore(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create([
            'user_id' => $manager->id,
            'starting_cash' => 100000,
            'opened_at' => now(),
        ]);
        $category = Category::create(['name' => 'Minuman']);
        $menu = Menu::create([
            'category_id' => $category->id,
            'name' => 'Kopi',
            'selling_price' => 10000,
            'is_available' => true,
        ]);

        return [$manager, $menu];
    }
}
