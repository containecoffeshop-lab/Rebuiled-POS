<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_mutations_are_audited_without_storing_request_payload(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->post(route('pos.categories.store'), [
            'name' => 'Minuman',
            'password' => 'must-not-be-audited',
        ])->assertRedirect();

        $event = AuditEvent::query()->firstOrFail();
        $this->assertSame($manager->id, $event->user_id);
        $this->assertSame('pos.categories.store', $event->route_name);
        $this->assertSame('POST', $event->method);
        $this->assertSame(302, $event->response_status);
        $this->assertArrayNotHasKey('password', $event->properties);
        $this->assertArrayNotHasKey('name', $event->properties);

        $this->get(route('pos.audit.index'))->assertOk()->assertSee('pos.categories.store');
        $this->get(route('pos.audit.show', $event))->assertOk()->assertSee('Rincian jejak aktivitas');
    }

    public function test_audit_history_is_manager_only(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)->get(route('pos.audit.index'))->assertForbidden();
    }

    public function test_audit_csv_uses_utf8_bom_and_protects_formula_cells(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'name' => '=SUM(1,1)']);
        AuditEvent::create([
            'user_id' => $manager->id,
            'route_name' => 'pos.categories.store',
            'method' => 'POST',
            'response_status' => 302,
            'properties' => ['path' => 'kasir/kategori', 'status' => 302],
        ]);

        $response = $this->actingAs($manager)->get(route('pos.audit.index', ['export' => 'csv']));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
    }
}
