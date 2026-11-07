<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\CycleCountTask;
use App\Models\Material;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_requires_assigned_open_shift_and_approval_applies_difference_to_latest_stock(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $unit = Unit::create(['name' => 'gram']);
        $material = Material::create(['name' => 'Biji kopi', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $inventory = app(RecipeInventory::class);
        $inventory->receive($material->id, 'FIRST', 10000, null);
        $this->actingAs($cashier)->post(route('pos.cycle-counts.store'), [])->assertForbidden();
        $this->actingAs($manager)->post(route('pos.cycle-counts.store'), [
            'task_date' => today()->toDateString(),
            'shift_number' => 2,
            'materials' => [$material->id],
        ])->assertSessionHasNoErrors();
        $task = CycleCountTask::firstOrFail();
        $payload = ['items' => [['id' => $task->items()->firstOrFail()->id, 'quantity' => 8]]];
        $this->actingAs($cashier)->post(route('pos.cycle-counts.count', $task), $payload)->assertSessionHasErrors('items');
        $shift = CashierShift::create(['user_id' => $cashier->id, 'shift_number' => 1, 'starting_cash' => 0, 'opened_at' => now()]);
        $this->actingAs($cashier)->post(route('pos.cycle-counts.count', $task), $payload)->assertSessionHasErrors('items');
        $shift->update(['shift_number' => 2]);
        $this->actingAs($cashier)->post(route('pos.cycle-counts.count', $task), $payload)->assertSessionHasNoErrors();
        $this->assertSame('review', $task->fresh()->status);
        $this->assertEquals(10, $inventory->onHandByMaterial([$material->id])->get($material->id));
        $inventory->receive($material->id, 'LATER', 5000, null);
        $this->actingAs($cashier)->post(route('pos.cycle-counts.approve', $task))->assertForbidden();
        $this->actingAs($manager)->post(route('pos.cycle-counts.approve', $task))->assertSessionHasNoErrors();
        $this->assertEquals(13, $inventory->onHandByMaterial([$material->id])->get($material->id));
        $item = $task->items()->firstOrFail();
        $this->assertEquals(-2, $item->difference);
        $this->assertEquals(15, $item->stock_before_approval);
        $this->assertEquals(13, $item->stock_after_approval);
        $this->actingAs($manager)->post(route('pos.cycle-counts.approve', $task))->assertSessionHasErrors('task');
    }

    public function test_open_shift_requires_explicit_shift_and_schedule_is_manager_only(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)->post(route('pos.shift.open'), ['starting_cash' => 0])->assertSessionHasErrors('shift_number');
        $this->actingAs($cashier)->post(route('pos.shift.open'), ['starting_cash' => 0, 'shift_number' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cashier_shifts', ['user_id' => $cashier->id, 'shift_number' => 2]);
        $this->actingAs($cashier)->put(route('pos.settings.shifts.update'), [])->assertForbidden();
        $this->actingAs($manager)->put(route('pos.settings.shifts.update'), ['shift_1_start' => '07:00'])->assertSessionHasErrors('shift_1_end');
        $this->actingAs($manager)->put(route('pos.settings.shifts.update'), ['shift_1_start' => '07:00', 'shift_1_end' => '15:00'])->assertSessionHasNoErrors();
    }

    public function test_approval_rejects_negative_stock_without_mutating_any_items(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $unit = Unit::create(['name' => 'liter']);
        $first = Material::create(['name' => 'Susu', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $second = Material::create(['name' => 'Sirup', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        $inventory = app(RecipeInventory::class);
        $inventory->receive($first->id, 'A', 10000, null);
        $inventory->receive($second->id, 'B', 10000, null);
        $this->actingAs($manager)->post(route('pos.cycle-counts.store'), ['task_date' => today()->toDateString(), 'shift_number' => 1, 'materials' => [$first->id, $second->id]])->assertSessionHasNoErrors();
        $task = CycleCountTask::firstOrFail();
        CashierShift::create(['user_id' => $cashier->id, 'shift_number' => 1, 'starting_cash' => 0, 'opened_at' => now()]);
        $items = $task->items()->orderBy('material_id')->get();
        $this->actingAs($cashier)->post(route('pos.cycle-counts.count', $task), ['items' => [
            ['id' => $items[0]->id, 'quantity' => 11],
            ['id' => $items[1]->id, 'quantity' => 2],
        ]])->assertSessionHasNoErrors();
        $inventory->removeFromBatch($second->batches()->firstOrFail()->id, 5000, 'Pemakaian setelah hitung');
        $this->actingAs($manager)->post(route('pos.cycle-counts.approve', $task))->assertSessionHasErrors('task');
        $this->assertSame('review', $task->fresh()->status);
        $this->assertEquals(10, $inventory->onHandByMaterial([$first->id])->get($first->id));
        $this->assertEquals(5, $inventory->onHandByMaterial([$second->id])->get($second->id));
        $this->actingAs($manager)->post(route('pos.cycle-counts.recount', $task))->assertSessionHasNoErrors();
        $this->assertSame('pending', $task->fresh()->status);
    }
}
