<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\StockTake;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockTakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_stock_take_records_snapshots_and_reconciles_fifo_stock_with_adjustment_movements(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $unit = Unit::create(['name' => 'gram']);
        $coffee = $this->material($unit->id, 'Biji kopi');
        $tea = $this->material($unit->id, 'Daun teh');
        $inventory = app(RecipeInventory::class);
        $inventory->receive($coffee->id, 'KOPI-OLD', 5_000, now()->addDays(3)->toDateString());
        $inventory->receive($coffee->id, 'KOPI-NEW', 7_000, now()->addDays(10)->toDateString());
        $this->actingAs($manager);

        $this->post(route('pos.stock-takes.store'), [
            'reference' => 'SO-20261106-001',
            'checked_at' => today()->toDateString(),
            'note' => 'Hitung setelah tutup',
            'items' => [
                ['material_id' => $coffee->id, 'counted_quantity' => '9.000', 'note' => 'Kurang tiga gram'],
                ['material_id' => $tea->id, 'counted_quantity' => '2.500'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $stockTake = StockTake::query()->with('details')->firstOrFail();
        $coffeeDetail = $stockTake->details->firstWhere('material_id', $coffee->id);
        $teaDetail = $stockTake->details->firstWhere('material_id', $tea->id);
        $this->assertSame('12.000', $coffeeDetail->system_quantity);
        $this->assertSame('9.000', $coffeeDetail->counted_quantity);
        $this->assertSame('-3.000', $coffeeDetail->difference);
        $this->assertSame('0.000', $teaDetail->system_quantity);
        $this->assertSame('2.500', $teaDetail->counted_quantity);
        $this->assertSame('2.500', $teaDetail->difference);
        $this->assertSame(3.0, (float) DB::table('stock_movements')->where('type', 'out')->where('description', 'like', '%SO-20261106-001%')->sum('quantity'));
        $this->assertSame(2.5, (float) DB::table('stock_movements')->where('type', 'in')->where('description', 'like', '%SO-20261106-001%')->sum('quantity'));
        $this->assertSame(9.0, (float) app(RecipeInventory::class)->onHandByMaterial([$coffee->id])->get($coffee->id));
        $this->assertSame(2.5, (float) app(RecipeInventory::class)->onHandByMaterial([$tea->id])->get($tea->id));

        $this->get(route('pos.stock-takes.index'))
            ->assertOk()
            ->assertSee('SO-20261106-001')
            ->assertSee('Kurang tiga gram');
    }

    public function test_cashiers_cannot_create_or_view_stock_takes_and_duplicate_material_rows_are_rejected(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier);

        $this->get(route('pos.stock-takes.index'))->assertForbidden();
        $this->post(route('pos.stock-takes.store'), [])->assertForbidden();

        $manager = User::factory()->create(['role' => 'manager']);
        $material = $this->material(Unit::create(['name' => 'ml'])->id, 'Susu');
        $this->actingAs($manager)->post(route('pos.stock-takes.store'), [
            'reference' => 'SO-20261106-002',
            'checked_at' => today()->toDateString(),
            'items' => [
                ['material_id' => $material->id, 'counted_quantity' => '1.000'],
                ['material_id' => $material->id, 'counted_quantity' => '2.000'],
            ],
        ])->assertSessionHasErrors('items.1.material_id');
        $this->assertDatabaseCount('stock_takes', 0);
    }

    private function material(int $unitId, string $name): Material
    {
        return Material::create([
            'name' => $name,
            'unit_id' => $unitId,
            'minimum_quantity' => 0,
        ]);
    }
}
