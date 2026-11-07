<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_are_earned_after_payment_redeemed_at_pos_and_reversed_after_refund(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create(['user_id' => $manager->id, 'starting_cash' => 100000, 'opened_at' => now()]);
        $menu = Menu::create([
            'category_id' => Category::create(['name' => 'Minuman'])->id,
            'name' => 'Kopi',
            'selling_price' => 10000,
            'is_available' => true,
        ]);
        $phone = '08123456789';

        $this->actingAs($manager)->put(route('pos.settings.loyalty.update'), [
            'earning_threshold' => 10000,
            'point_value' => 1000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('orders.store'), [
            'customer_name' => 'Rina',
            'customer_phone' => $phone,
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 2]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $onlineOrder = Transaction::query()->firstOrFail();
        $customer = Customer::query()->where('phone', $phone)->firstOrFail();
        $this->assertSame(0, $onlineOrder->loyalty_points_earned);
        $this->assertSame(0, (int) $customer->pointEntries()->sum('points'));

        $this->actingAs($manager)->patch(route('pos.orders.paid', $onlineOrder), [
            'payment_method' => 'cash',
            'amount_paid' => $onlineOrder->grand_total,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $onlineOrder->fresh()->loyalty_points_earned);
        $this->assertSame(2, (int) $customer->pointEntries()->sum('points'));

        $quote = $this->actingAs($manager)->postJson(route('pos.loyalty.quote'), [
            'customer_phone' => $phone,
            'redeem_points' => 2,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertOk()->assertJson([
            'loyalty_points_balance' => 2,
            'loyalty_points_redeemed' => 2,
            'loyalty_discount' => 2000,
            'grand_total' => 8000,
        ]);
        $this->assertSame(200, $quote->getStatusCode());

        $this->post(route('pos.orders.store'), [
            'customer_name' => 'Rina',
            'customer_phone' => $phone,
            'transaction_type' => 'takeaway',
            'payment_method' => 'cash',
            'amount_paid' => 8000,
            'redeem_points' => 2,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $posSale = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame(2000, $posSale->loyalty_discount);
        $this->assertSame(2, $posSale->loyalty_points_redeemed);
        $this->assertSame(0, (int) $customer->pointEntries()->sum('points'));

        $this->post(route('pos.returns.store'), [
            'transaction_id' => $onlineOrder->id,
            'return_date' => today()->toDateString(),
            'refund_method' => 'refund',
            'items' => [[
                'transaction_detail_id' => $onlineOrder->details()->value('id'),
                'quantity' => 1,
                'reason' => 'Salah pesan',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $return = TransactionReturn::query()->firstOrFail();
        $this->actingAs($manager)->patch(route('pos.returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(-1, (int) $customer->pointEntries()->sum('points'));
        $this->assertDatabaseHas('loyalty_point_entries', [
            'customer_id' => $customer->id,
            'transaction_id' => $onlineOrder->id,
            'transaction_return_id' => $return->id,
            'entry_type' => 'return_reversal',
            'points' => -1,
        ]);

        $this->post(route('pos.returns.store'), [
            'transaction_id' => $onlineOrder->id,
            'return_date' => today()->toDateString(),
            'refund_method' => 'replacement',
            'items' => [[
                'transaction_detail_id' => $onlineOrder->details()->value('id'),
                'quantity' => 1,
                'reason' => 'Klaim penggantian',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $replacementReturn = TransactionReturn::query()->latest('id')->firstOrFail();
        $this->actingAs($manager)->patch(route('pos.returns.confirm', $replacementReturn))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(-2, (int) $customer->pointEntries()->sum('points'));
        $this->assertDatabaseHas('loyalty_point_entries', [
            'customer_id' => $customer->id,
            'transaction_id' => $onlineOrder->id,
            'transaction_return_id' => $replacementReturn->id,
            'entry_type' => 'return_reversal',
            'points' => -1,
        ]);
    }

    public function test_online_customers_cannot_submit_or_quote_point_redemptions_and_cashiers_cannot_configure_loyalty(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)->put(route('pos.settings.loyalty.update'), [
            'earning_threshold' => 10000,
            'point_value' => 100,
        ])->assertForbidden();

        $menu = Menu::create([
            'category_id' => Category::create(['name' => 'Minuman'])->id,
            'name' => 'Teh',
            'selling_price' => 5000,
            'is_available' => true,
        ]);

        $this->postJson(route('coupons.quote'), [
            'redeem_points' => 0,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('redeem_points');

        $this->post(route('orders.store'), [
            'customer_name' => 'Dina',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'redeem_points' => 1,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('redeem_points');
        $this->assertDatabaseCount('transactions', 0);
    }
}
