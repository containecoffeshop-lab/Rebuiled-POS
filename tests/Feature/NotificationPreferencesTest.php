<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Unit;
use App\Models\User;
use App\Services\PosNotificationService;
use App\Services\RecipeInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_notifies_manager_once_until_stock_recovers_and_drops_again(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $unit = Unit::create(['name' => 'kg']);
        $material = Material::create(['unit_id' => $unit->id, 'name' => 'Kopi', 'minimum_quantity' => 5]);

        $this->postJson(route('pos.notifications.stock'))->assertUnauthorized();
        $this->actingAs($cashier)->postJson(route('pos.notifications.stock'))->assertOk();
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $cashier->notifications()->count());
        $this->assertSame('stock', $manager->notifications()->firstOrFail()->data['category']);
        $this->actingAs($manager)->postJson(route('pos.notifications.stock'))->assertOk();
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertDatabaseCount('audit_events', 0);

        $inventory = app(RecipeInventory::class);
        $inventory->receive($material->id, 'BACKUP-STOCK', 6000, null);
        $this->actingAs($manager)->postJson(route('pos.notifications.stock'))->assertOk();
        $batch = $material->batches()->firstOrFail();
        $inventory->removeFromBatch($batch->id, 2000, 'Uji stok');
        $this->actingAs($manager)->postJson(route('pos.notifications.stock'))->assertOk();
        $this->assertSame(2, $manager->notifications()->count());
    }

    public function test_manager_can_choose_categories_by_role_for_future_events(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $inactive = User::factory()->create(['role' => 'cashier', 'is_active' => false]);
        $service = app(PosNotificationService::class);

        $service->notifyStaff('Pertama', 'Pesanan', '/kasir', 'orders');
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(1, $cashier->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());

        $this->actingAs($manager)->put(route('pos.settings.notifications.update'), [
            'categories' => ['manager' => ['shifts'], 'cashier' => ['orders', 'sales']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $service->notifyStaff('Kedua', 'Pesanan', '/kasir', 'orders');
        $service->notifyStaff('Ketiga', 'Shift', '/kasir', 'shifts');
        $this->assertSame(2, $manager->notifications()->count());
        $this->assertSame(2, $cashier->notifications()->count());
        $this->assertSame('shifts', $manager->notifications()->where('data', 'like', '%Ketiga%')->firstOrFail()->data['category']);
        $this->actingAs($manager)->get(route('pos.settings.index'))->assertOk()->assertSee('Notifikasi per peran');
    }

    public function test_preferences_can_disable_every_category_and_are_manager_only(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAs($cashier)->put(route('pos.settings.notifications.update'), [])->assertForbidden();
        $this->actingAs($manager)->put(route('pos.settings.notifications.update'), [
            'categories' => ['manager' => ['unknown']],
        ])->assertSessionHasErrors();
        $this->actingAs($manager)->put(route('pos.settings.notifications.update'), [])->assertSessionHasNoErrors();
        app(PosNotificationService::class)->notifyStaff('Tes', 'Pesan', '/kasir', 'sales');
        $this->assertSame(0, $manager->notifications()->count());
        $this->assertSame(0, $cashier->notifications()->count());
    }
}
