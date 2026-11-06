<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\StockAuditTask;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_audit_requires_two_different_sequential_shift_counts_and_tracks_task_code(): void
    {
        $shiftOne = User::factory()->create(['role' => 'manager']);
        $shiftTwo = User::factory()->create(['role' => 'manager']);
        $unit = Unit::create(['name' => 'gram']);
        $coffee = Material::create(['name' => 'Biji kopi audit', 'unit_id' => $unit->id, 'minimum_quantity' => 0]);
        app(RecipeInventory::class)->receive($coffee->id, 'AUDIT-COFFEE', 8_000, null);

        $this->actingAs($shiftOne)->post(route('pos.stock-audits.shift1'), [
            'note' => 'Audit akhir pekan',
            'items' => [[
                'material_id' => $coffee->id,
                'counted_quantity' => '7.500',
                'note' => 'Hitung pertama',
            ]],
        ])->assertRedirect(route('pos.stock-audits.index'))->assertSessionHasNoErrors();

        $task = StockAuditTask::query()->with('items')->firstOrFail();
        $this->assertMatchesRegularExpression('/^TASK-\d{8}-[A-Z0-9]{6}$/', $task->task_code);
        $this->assertSame('awaiting_shift2', $task->status);
        $this->assertSame('8.000', $task->items->first()->system_quantity);
        $this->assertSame('7.500', $task->items->first()->shift1_quantity);
        $this->assertNull($task->items->first()->shift2_quantity);

        $this->actingAs($shiftOne)->post(route('pos.stock-audits.shift2', $task), [
            'task_code' => $task->task_code,
            'items' => [['item_id' => $task->items->first()->id, 'counted_quantity' => '7.250']],
        ])->assertSessionHasErrors('items');
        $this->assertSame('awaiting_shift2', $task->fresh()->status);

        $this->actingAs($shiftTwo)->post(route('pos.stock-audits.shift2', $task), [
            'task_code' => 'TASK-KODE-SALAH',
            'items' => [['item_id' => $task->items->first()->id, 'counted_quantity' => '7.250']],
        ])->assertSessionHasErrors('task_code');

        $this->actingAs($shiftTwo)->post(route('pos.stock-audits.shift2', $task), [
            'task_code' => $task->task_code,
            'items' => [['item_id' => $task->items->first()->id, 'counted_quantity' => '7.250']],
        ])->assertRedirect(route('pos.stock-audits.index'))->assertSessionHasNoErrors();

        $task->refresh()->load('items');
        $this->assertSame('completed', $task->status);
        $this->assertSame($shiftTwo->id, $task->shift2_user_id);
        $this->assertSame('7.250', $task->items->first()->shift2_quantity);
        $this->assertSame(8.0, (float) app(RecipeInventory::class)->onHandByMaterial([$coffee->id])->get($coffee->id));
        $this->actingAs($shiftOne)->get(route('pos.stock-audits.index'))
            ->assertOk()
            ->assertSee($task->task_code)
            ->assertSee('Selisih Shift 1, Shift 2');
    }

    public function test_stock_audit_is_manager_only(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('pos.stock-audits.index'))->assertForbidden();
        $this->post(route('pos.stock-audits.shift1'), [])->assertForbidden();
    }
}
