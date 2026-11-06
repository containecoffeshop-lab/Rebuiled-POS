<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankTransferAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_configured_transfer_account_is_snapshotted_on_pos_and_online_payments(): void
    {
        [$manager, $cashier, $menu] = $this->setupStore();

        $this->actingAs($manager)->put(route('pos.settings.accounts.update'), [
            'accounts' => [[
                'bank_name' => 'Bank Contoh',
                'account_number' => '001234567890',
                'account_name' => 'Pemilik Contoh',
                'is_active' => 1,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $account = BankAccount::query()->firstOrFail();
        $this->get(route('home'))->assertOk()->assertDontSee('001234567890');

        $this->actingAs($cashier)->post(route('pos.orders.store'), [
            'customer_name' => 'Pelanggan POS',
            'transaction_type' => 'takeaway',
            'payment_method' => 'transfer',
            'bank_account_id' => $account->id,
            'amount_paid' => 10000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $posSale = Transaction::query()->where('status', 'paid')->firstOrFail();
        $this->assertSame($account->id, $posSale->bank_account_id);

        $this->post(route('orders.store'), [
            'customer_name' => 'Pelanggan Online',
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $onlineOrder = Transaction::query()->where('status', 'pending')->firstOrFail();
        $this->actingAs($cashier)->patch(route('pos.orders.paid', $onlineOrder), [
            'payment_method' => 'transfer',
            'bank_account_id' => $account->id,
            'amount_paid' => 10000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($account->id, $onlineOrder->fresh()->bank_account_id);

        $period = ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()];
        $this->actingAs($manager)->get(route('pos.reports.index', ['report' => 'cash-flow'] + $period))
            ->assertOk()
            ->assertSee('Bank Contoh');
        $csv = $this->actingAs($manager)->get(route('pos.reports.export', ['report' => 'cash-flow'] + $period))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Bank Contoh', $csv);

        $this->actingAs($manager)->put(route('pos.settings.accounts.update'), [
            'accounts' => [[
                'id' => $account->id,
                'bank_name' => 'Bank Contoh',
                'account_number' => '001234567890',
                'account_name' => 'Pemilik Contoh',
                'is_active' => 0,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id, 'is_active' => false]);
        $this->assertSame($account->id, $posSale->fresh()->bank_account_id);
    }

    public function test_manager_only_manages_accounts_and_inactive_accounts_cannot_be_selected(): void
    {
        [$manager, $cashier, $menu] = $this->setupStore();
        $account = BankAccount::create([
            'bank_name' => 'Bank Contoh',
            'account_number' => '99887766',
            'account_name' => 'Pemilik Contoh',
            'is_active' => false,
        ]);

        $this->actingAs($cashier)->put(route('pos.settings.accounts.update'), [
            'accounts' => [],
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('pos.orders.store'), [
            'customer_name' => 'Pelanggan POS',
            'transaction_type' => 'takeaway',
            'payment_method' => 'transfer',
            'bank_account_id' => $account->id,
            'amount_paid' => 10000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('bank_account_id');
        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * @return array{User, User, Menu}
     */
    private function setupStore(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);
        CashierShift::create([
            'user_id' => $manager->id,
            'starting_cash' => 100000,
            'opened_at' => now(),
        ]);
        CashierShift::create([
            'user_id' => $cashier->id,
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

        return [$manager, $cashier, $menu];
    }
}
