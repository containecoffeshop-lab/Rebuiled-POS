<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftClosingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_shows_only_current_shift_sales_paid_on_closing_date(): void
    {
        Carbon::setTestNow('2026-11-07 02:00:00');
        $cashier = User::factory()->create(['role' => 'cashier', 'module_permissions' => ['kasir']]);
        $other = User::factory()->create(['role' => 'cashier', 'module_permissions' => ['kasir']]);
        $shift = CashierShift::create([
            'user_id' => $cashier->id, 'shift_number' => 2,
            'starting_cash' => 50000, 'opened_at' => '2026-11-06 22:00:00',
        ]);
        $otherShift = CashierShift::create([
            'user_id' => $other->id, 'shift_number' => 1,
            'starting_cash' => 0, 'opened_at' => now(),
        ]);
        $this->sale($shift, 'INV-BEFORE', 'cash', 7000, '2026-11-06 23:00:00');
        $this->sale($shift, 'INV-CASH', 'cash', 20000, '2026-11-07 00:30:00');
        $this->sale($shift, 'INV-TRANSFER', 'transfer', 10000, '2026-11-07 01:00:00');
        $this->sale($shift, 'INV-CANCELLED', 'cash', 5000, '2026-11-07 01:30:00', 'cancelled');
        $this->sale($otherShift, 'INV-OTHER', 'cash', 9000, '2026-11-07 01:30:00');

        $this->actingAs($cashier)->post(route('pos.shift.close'), ['ending_cash' => 77000])
            ->assertRedirect(route('pos.shift.report', $shift))->assertSessionHasNoErrors();

        $this->get(route('pos.shift.report', $shift))
            ->assertOk()
            ->assertViewHas('cashSales', 20000)
            ->assertViewHas('transferSales', 10000)
            ->assertViewHas('totalSales', 30000)
            ->assertViewHas('afterStartingCash', -20000)
            ->assertSee('INV-CASH')
            ->assertSee('INV-TRANSFER')
            ->assertDontSee('INV-BEFORE')
            ->assertDontSee('INV-CANCELLED')
            ->assertDontSee('INV-OTHER')
            ->assertSee('Rp-20.000')
            ->assertSee('Rp77.000')
            ->assertSee('Cetak ringkasan')
            ->assertSee('data-shift-report-dialog', false)
            ->assertSee('window.print()', false);
        $this->assertSame(77000, $shift->fresh()->expected_cash);
    }

    public function test_report_is_private_and_requires_a_closed_shift(): void
    {
        $owner = User::factory()->create(['role' => 'cashier', 'module_permissions' => ['kasir']]);
        $other = User::factory()->create(['role' => 'manager']);
        $shift = CashierShift::create([
            'user_id' => $owner->id, 'starting_cash' => 10000, 'opened_at' => now(),
        ]);

        $this->get(route('pos.shift.report', $shift))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('pos.shift.report', $shift))->assertNotFound();
        $this->post(route('pos.shift.close'), ['ending_cash' => 10000])->assertRedirect(route('pos.shift.report', $shift));
        $this->actingAs($other)->get(route('pos.shift.report', $shift))->assertNotFound();
        $this->actingAs($owner)->get(route('pos.shift.report', $shift))
            ->assertOk()->assertViewHas('afterStartingCash', -10000)
            ->assertSee('Tidak ada transaksi lunas pada tanggal penutupan.');
    }

    private function sale(CashierShift $shift, string $invoice, string $method, int $amount, string $paidAt, string $status = 'paid'): void
    {
        Transaction::create([
            'cashier_shift_id' => $shift->id, 'invoice' => $invoice, 'customer_name' => 'Pelanggan',
            'transaction_kind' => 'sale', 'status' => $status, 'payment_method' => $method,
            'paid_at' => $paidAt, 'subtotal' => $amount, 'grand_total' => $amount,
        ]);
    }
}
