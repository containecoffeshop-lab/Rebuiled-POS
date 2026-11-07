<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PosNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

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
