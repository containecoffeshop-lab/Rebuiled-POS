<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InitialSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_manager_can_register_once_and_setup_is_then_closed(): void
    {
        $this->get(route('setup'))->assertOk();

        $this->post(route('setup.store'), [
            'name' => 'Pengelola',
            'email' => 'owner@example.test',
            'password' => 'password-aman-123',
            'password_confirmation' => 'password-aman-123',
        ])->assertRedirect(route('pos.index'));

        $this->assertAuthenticatedAs(User::query()->firstOrFail());
        $this->assertTrue((bool) DB::table('setup_state')->where('id', 1)->value('is_complete'));
        $this->post(route('logout'));
        $this->get(route('setup'))->assertRedirect(route('login'));

        $this->post(route('setup.store'), [
            'name' => 'Another manager',
            'email' => 'another@example.test',
            'password' => 'another-password-123',
            'password_confirmation' => 'another-password-123',
        ])->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_production_setup_requires_the_configured_one_time_token(): void
    {
        config([
            'app.env' => 'production',
            'app.initial_setup_token' => 'one-time-manager-setup',
        ]);

        $this->get(route('setup'))->assertOk();

        $details = [
            'name' => 'Pengelola',
            'email' => 'owner@example.test',
            'password' => 'password-aman-123',
            'password_confirmation' => 'password-aman-123',
        ];

        $this->post(route('setup.store'), $details)->assertForbidden();
        $this->post(route('setup.store'), [...$details, 'setup_token' => 'wrong-token'])->assertForbidden();

        $this->post(route('setup.store'), [...$details, 'setup_token' => 'one-time-manager-setup'])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_production_setup_is_closed_when_a_token_is_not_configured(): void
    {
        config(['app.env' => 'production', 'app.initial_setup_token' => null]);

        $this->get(route('setup'))->assertNotFound();
        $this->post(route('setup.store'), [
            'name' => 'Pengelola',
            'email' => 'owner@example.test',
            'password' => 'password-aman-123',
            'password_confirmation' => 'password-aman-123',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }
}
