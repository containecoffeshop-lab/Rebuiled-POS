<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SummaryResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_starts_a_new_period_without_deleting_history_and_keeps_active_orders_visible(): void
    {
        Carbon::setTestNow('2026-11-07 10:00:00');
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier', 'module_permissions' => ['ringkasan', 'laporan']]);
        CashierShift::create(['user_id' => $manager->id, 'starting_cash' => 0, 'opened_at' => now()]);
        $menu = Menu::create(['name' => 'Kopi', 'category_id' => Category::create(['name' => 'Minuman'])->id, 'selling_price' => 22000, 'is_available' => true]);
        $this->actingAs($manager)->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway', 'payment_method' => 'cash', 'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();
        $this->post(route('orders.store'), [
            'customer_name' => 'Pesanan aktif', 'customer_phone' => '08123456789', 'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect();
        $category = ExpenseCategory::create(['name' => 'Biaya']);
        $subcategory = ExpenseSubcategory::create(['expense_category_id' => $category->id, 'name' => 'Umum']);
        $expense = Expense::create([
            'expense_code' => 'OLD', 'expense_date' => today(), 'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id, 'created_by' => $manager->id,
            'amount' => 12000, 'payment_status' => 'paid',
        ]);
        DB::table('expense_payments')->insert(['expense_id' => $expense->id, 'paid_at' => today(), 'amount' => 12000, 'payment_method' => 'cash', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($cashier)->post(route('pos.summary.reset'), ['confirmation' => 'RESET'])->assertForbidden();
        $this->actingAs($manager)->post(route('pos.summary.reset'), [])->assertSessionHasErrors('confirmation');
        $this->get(route('pos.index', ['module' => 'ringkasan']))->assertViewHas('monthlySummary', fn ($summary) => (int) $summary['income'] === 22000 && (int) $summary['expenses'] === 12000);
        $this->post(route('pos.summary.reset'), ['confirmation' => 'RESET'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseCount('expenses', 1);
        $this->get(route('pos.index', ['module' => 'ringkasan']))
            ->assertViewHas('summary', fn ($summary) => (int) $summary['sales_today'] === 0 && $summary['awaiting_payment'] === 1 && $summary['in_kitchen'] === 2)
            ->assertViewHas('monthlySummary', fn ($summary) => (int) $summary['income'] === 0 && (int) $summary['expenses'] === 0 && $summary['operational_cost'] === 0);
        $this->get(route('pos.summary.pdf'))->assertOk()->assertDontSee('OLD')->assertSee('Tidak ada transaksi lunas pada periode ini.');

        Carbon::setTestNow('2026-11-07 10:00:02');
        $pending = Transaction::query()->where('status', 'pending')->firstOrFail();
        $this->patch(route('pos.orders.paid', $pending), ['payment_method' => 'cash', 'amount_paid' => 22000])
            ->assertSessionHasNoErrors();
        $this->post(route('pos.orders.store'), [
            'transaction_type' => 'takeaway', 'payment_method' => 'cash', 'amount_paid' => 22000,
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();
        $newExpense = Expense::create([
            'expense_code' => 'NEW', 'expense_date' => today(), 'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id, 'created_by' => $manager->id,
            'amount' => 4000, 'payment_status' => 'paid',
        ]);
        $backdated = Expense::create([
            'expense_code' => 'BACKDATED', 'expense_date' => today()->subDay(), 'expense_category_id' => $category->id,
            'expense_subcategory_id' => $subcategory->id, 'created_by' => $manager->id,
            'amount' => 7000, 'payment_status' => 'unpaid',
        ]);
        DB::table('expense_payments')->insert(['expense_id' => $newExpense->id, 'paid_at' => today(), 'amount' => 4000, 'payment_method' => 'cash', 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('pos.index', ['module' => 'ringkasan']))
            ->assertViewHas('summary', fn ($summary) => (int) $summary['sales_today'] === 44000 && $summary['awaiting_payment'] === 0)
            ->assertViewHas('monthlySummary', fn ($summary) => (int) $summary['income'] === 44000 && (int) $summary['expenses'] === 4000 && $summary['operational_cost'] === 4000);
        $this->get(route('pos.summary.pdf'))->assertOk()->assertSee('NEW')->assertDontSee('OLD')->assertDontSee('BACKDATED');
        $this->assertDatabaseHas('expenses', ['id' => $backdated->id]);
        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseHas('audit_events', ['route_name' => 'pos.summary.reset']);
        $this->post(route('pos.summary.reset'), ['confirmation' => 'RESET'])->assertSessionHasNoErrors();
        $this->get(route('pos.index', ['module' => 'ringkasan']))
            ->assertViewHas('monthlySummary', fn ($summary) => (int) $summary['income'] === 0 && (int) $summary['expenses'] === 0);
        $this->assertDatabaseCount('transactions', 3);

        Carbon::setTestNow();
    }
}
