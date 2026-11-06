<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Material;
use App\Models\Menu;
use App\Models\MenuRecipe;
use App\Models\StoreCharge;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_recipe_sale_snapshots_batch_cogs_and_reports_gross_profit(): void
    {
        [$manager, $menu] = $this->setupSaleEnvironment();
        $inventory = app(RecipeInventory::class);
        $material = Material::query()->firstOrFail();
        $inventory->receive($material->id, 'COSTED-001', 10_000, null, 'Stok masuk', '1000.000000');
        $sale = $this->payOnlineOrder($manager, $menu);

        $this->assertTrue($sale->fresh()->cogs_complete);
        $this->assertSame(2000, $sale->fresh()->cogs_total);
        $this->assertDatabaseHas('stock_movements', [
            'transaction_id' => $sale->id,
            'unit_cost' => '1000.000000',
            'cost_amount' => 2000,
        ]);

        $this->actingAs($manager)->get(route('pos.reports.index', [
            'report' => 'profit-loss',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()
            ->assertSee('Laba total periode')
            ->assertSee('Rp8.000')
            ->assertSee('Lengkap');

        $csv = $this->actingAs($manager)->get(route('pos.reports.export', [
            'report' => 'profit-loss',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();
        $this->assertStringContainsString('Pendapatan transaksi di luar PJK', $csv);
        $this->assertStringContainsString('Laba kotor sebelum refund', $csv);
        $this->assertStringContainsString($sale->invoice, $csv);
    }

    public function test_profit_total_is_withheld_when_sales_have_missing_recipe_or_batch_costs(): void
    {
        [$manager, $menu] = $this->setupSaleEnvironment();
        $this->app->make(RecipeInventory::class)->receive(Material::query()->firstOrFail()->id, 'UNKNOWN-COST', 10_000, null);
        $unknownCostSale = $this->payOnlineOrder($manager, $menu, 'tanpa biaya');
        $this->assertFalse($unknownCostSale->fresh()->cogs_complete);
        $this->assertNull($unknownCostSale->fresh()->cogs_total);

        $untrackedMenu = $this->menu('Minuman tanpa resep', 12000);
        $untrackedSale = $this->payOnlineOrder($manager, $untrackedMenu, 'tanpa resep');
        $this->assertFalse($untrackedSale->fresh()->cogs_complete);

        $this->actingAs($manager)->get(route('pos.reports.index', [
            'report' => 'profit-loss',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()
            ->assertSee('Belum tersedia')
            ->assertSee('0 / 2');
    }

    public function test_gross_profit_excludes_pjk_and_includes_service_fee_income(): void
    {
        [$manager, $menu] = $this->setupSaleEnvironment();
        StoreCharge::create(['code' => 'PJK', 'name' => 'Pajak', 'value' => '10%', 'is_active' => true]);
        StoreCharge::create(['code' => 'OPR', 'name' => 'Layanan', 'value' => '500', 'is_active' => true]);
        $this->app->make(RecipeInventory::class)->receive(Material::query()->firstOrFail()->id, 'COSTED-TAX', 10000, null, 'Stok masuk', '1000.000000');
        $sale = $this->payOnlineOrder($manager, $menu);

        $this->assertSame(11500, $sale->grand_total);
        $this->assertSame(2000, $sale->cogs_total);
        $this->actingAs($manager)->get(route('pos.reports.index', [
            'report' => 'profit-loss',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()
            ->assertSee('Rp10.500')
            ->assertSee('Rp8.500');

        $sale->load('details');
        $this->actingAs($manager)->post(route('pos.returns.store'), [
            'transaction_id' => $sale->id,
            'return_date' => today()->toDateString(),
            'refund_method' => 'refund',
            'items' => [[
                'transaction_detail_id' => $sale->details->firstOrFail()->id,
                'quantity' => 1,
                'reason' => 'Pengembalian penuh',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $return = TransactionReturn::query()->firstOrFail();
        $this->actingAs($manager)->patch(route('pos.returns.confirm', $return))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->get(route('pos.reports.index', [
            'report' => 'profit-loss',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]))->assertOk()
            ->assertSee('Refund pengurang pendapatan (di luar PJK)')
            ->assertSee('Rp10.500')
            ->assertSee('Rp-2.000');
    }

    /**
     * @return array{User, Menu}
     */
    private function setupSaleEnvironment(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        CashierShift::create([
            'user_id' => $manager->id,
            'starting_cash' => 100000,
            'opened_at' => now(),
        ]);
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create([
            'name' => 'Biji kopi',
            'unit_id' => $unit->id,
            'minimum_quantity' => 0,
        ]);
        $menu = $this->menu('Kopi', 10000);
        MenuRecipe::create(['menu_id' => $menu->id, 'material_id' => $material->id, 'quantity' => 2]);

        return [$manager, $menu];
    }

    private function payOnlineOrder(User $manager, Menu $menu, string $customer = 'Nadia'): Transaction
    {
        $this->post(route('orders.store'), [
            'customer_name' => $customer,
            'customer_phone' => '08123456789',
            'transaction_type' => 'takeaway',
            'items' => [['menu_id' => $menu->id, 'quantity' => 1]],
        ])->assertRedirect();
        $sale = Transaction::query()->latest('id')->firstOrFail();

        $this->actingAs($manager)->patch(route('pos.orders.paid', $sale), [
            'payment_method' => 'cash',
            'amount_paid' => $sale->grand_total,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $sale->fresh();
    }

    private function menu(string $name, int $price): Menu
    {
        $category = Category::firstOrCreate(['name' => 'Minuman']);

        return Menu::create([
            'category_id' => $category->id,
            'name' => $name,
            'selling_price' => $price,
            'is_available' => true,
        ]);
    }
}
