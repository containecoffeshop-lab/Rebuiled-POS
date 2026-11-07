<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PosNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModulePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_grant_and_revoke_module_access_without_granting_admin(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('pos.reports.index'))->assertForbidden();
        $this->actingAs($manager)->put(route('pos.users.permissions', $cashier), ['permissions' => ['laporan', 'cycle_count']])->assertSessionHasNoErrors();
        $cashier->refresh();
        $this->actingAs($cashier)->get(route('pos.reports.index'))->assertOk();
        $this->actingAs($cashier)->get(route('pos.settings.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('pos.users.index'))->assertForbidden();
        $this->actingAs($cashier)->post(route('pos.orders.store'), [])->assertForbidden();
        $this->actingAs($cashier)->patch(route('pos.orders.kitchen.complete-all'))->assertForbidden();
        $this->actingAs($cashier)->get(route('pos.index'))->assertOk()->assertDontSee('data-pos-module="pesanan"', false)->assertDontSee('data-pos-module="kasir"', false);
        $this->actingAs($manager)->put(route('pos.users.permissions', $cashier), [])->assertSessionHasNoErrors();
        $cashier->refresh();
        $this->actingAs($cashier)->get(route('pos.reports.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('pos.cycle-counts.index'))->assertForbidden();
    }

    public function test_permissions_cannot_be_changed_by_cashiers_or_on_manager_accounts(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($cashier)->put(route('pos.users.permissions', $cashier), ['permissions' => ['laporan']])->assertForbidden();
        $this->actingAs($manager)->put(route('pos.users.permissions', $manager), ['permissions' => ['laporan']])->assertSessionHasErrors('permissions');
        $this->actingAs($manager)->put(route('pos.users.permissions', $cashier), ['permissions' => ['settings']])->assertSessionHasErrors('permissions.0');
    }

    public function test_notifications_do_not_disclose_revoked_module_data(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $cashier = User::factory()->create(['role' => 'cashier', 'module_permissions' => ['pesanan']]);
        app(PosNotificationService::class)->notifyStaff('Penjualan', 'Invoice', '/kasir', 'sales');
        $this->actingAs($cashier)->getJson(route('pos.notifications.index'))->assertJsonPath('unread_count', 1);
        $this->actingAs($manager)->put(route('pos.users.permissions', $cashier), [])->assertSessionHasNoErrors();
        $cashier->refresh();
        $this->actingAs($cashier)->getJson(route('pos.notifications.index'))->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'notifications');
    }

    public function test_legacy_notifications_follow_current_permissions(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'module_permissions' => ['pesanan']]);
        $cashier->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'legacy',
            'data' => ['title' => 'Pesanan online masuk', 'message' => 'Pesanan lama', 'url' => '/kasir'],
        ]);
        $this->actingAs($cashier)->getJson(route('pos.notifications.index'))->assertJsonPath('unread_count', 1);
        $cashier->update(['module_permissions' => []]);
        $this->actingAs($cashier)->getJson(route('pos.notifications.index'))->assertJsonPath('unread_count', 0);
    }
}
