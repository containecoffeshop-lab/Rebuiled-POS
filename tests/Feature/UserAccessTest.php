<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_and_manage_user_accounts(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->get(route('pos.users.index'))->assertOk()->assertSee('Pengguna');

        $this->post(route('pos.users.store'), [
            'name' => 'Kasir Pagi',
            'email' => 'kasir@example.test',
            'role' => 'cashier',
            'password' => 'long-password-123',
            'password_confirmation' => 'long-password-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $cashier = User::query()->where('email', 'kasir@example.test')->firstOrFail();
        $this->assertSame('cashier', $cashier->role);
        $this->assertTrue($cashier->is_active);
        $this->assertNotSame('long-password-123', $cashier->password);

        $this->patch(route('pos.users.toggle', $cashier))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $cashier->id, 'is_active' => false]);

        $this->patch(route('pos.users.toggle', $cashier))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $cashier->id, 'is_active' => true]);
    }

    public function test_cashier_can_work_pos_but_cannot_access_manager_routes_or_actions(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('pos.index'))->assertOk();
        $this->get(route('pos.users.index'))->assertForbidden();
        $this->get(route('pos.purchases.index'))->assertForbidden();
        $this->get(route('pos.summary.export', ['month' => now()->format('Y-m')]))->assertForbidden();
        $this->post(route('pos.categories.store'), ['name' => 'Minuman'])->assertForbidden();
        $this->post(route('pos.users.store'), [])->assertForbidden();
    }

    public function test_account_cannot_demote_or_deactivate_the_last_manager(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $this->patch(route('pos.users.update', $manager), [
            'name' => $manager->name,
            'email' => $manager->email,
            'role' => 'cashier',
        ])->assertSessionHasErrors('role');

        $this->patch(route('pos.users.toggle', $manager))->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $manager->id, 'role' => 'manager', 'is_active' => true]);
    }

    public function test_deactivated_user_is_logged_out_from_protected_pages(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => false]);

        $this->actingAs($cashier)->get(route('pos.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_accepts_only_active_accounts(): void
    {
        $inactive = User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => 'valid-password-123',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $inactive->email,
            'password' => 'valid-password-123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $active = User::factory()->create([
            'email' => 'active@example.test',
            'password' => 'valid-password-123',
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $active->email,
            'password' => 'valid-password-123',
        ])->assertRedirect(route('pos.index'));
        $this->assertAuthenticatedAs($active);
    }

    public function test_login_session_survives_refresh_requests_and_is_marked_for_the_local_day(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:15:00', 'Asia/Jakarta'));
        $active = User::factory()->create([
            'email' => 'daily@example.test',
            'password' => 'valid-password-123',
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $active->email,
            'password' => 'valid-password-123',
            'remember' => '1',
        ])->assertRedirect(route('pos.index'))
            ->assertSessionHas('auth_day', '2026-11-06');

        $this->get(route('pos.index'))->assertOk();
        $this->get(route('home'))->assertOk();
        $this->assertAuthenticatedAs($active);
        $this->assertGreaterThanOrEqual(1440, config('session.lifetime'));

        Carbon::setTestNow();
    }

    public function test_login_session_expires_at_midnight_in_the_store_timezone(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-11-06 23:59:00', 'Asia/Jakarta'));
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->withSession(['auth_day' => '2026-11-06'])
            ->get(route('home'))
            ->assertOk()
            ->assertSessionHas('auth_day', '2026-11-06');

        Carbon::setTestNow(Carbon::parse('2026-11-07 00:01:00', 'Asia/Jakarta'));
        $this->get(route('pos.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Sesi login berakhir tengah malam. Silakan masuk kembali.');
        $this->assertGuest();

        Carbon::setTestNow();
    }

    public function test_explicit_logout_clears_the_daily_authenticated_session(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->withSession(['auth_day' => now()->toDateString()])
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->get(route('pos.index'))->assertRedirect(route('login'));
    }

    public function test_manager_cannot_demote_or_disable_their_own_account(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);

        $this->patch(route('pos.users.update', $manager), [
            'name' => $manager->name,
            'email' => $manager->email,
            'role' => 'cashier',
        ])->assertSessionHasErrors('role');

        $this->patch(route('pos.users.toggle', $manager))->assertSessionHasErrors('user');
    }
}
