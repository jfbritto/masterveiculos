<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_requires_token(): void
    {
        $response = $this->postJson('/api/webhook/heartbeat', []);
        $response->assertStatus(401);
    }

    public function test_heartbeat_rejects_invalid_token(): void
    {
        $response = $this->postJson('/api/webhook/heartbeat', [], [
            'X-Master-Token' => 'invalid-token',
        ]);
        $response->assertStatus(401);
    }

    public function test_heartbeat_accepts_valid_token_and_creates_stats(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/api/webhook/heartbeat', [
            'vehicles_count' => 42,
            'leads_count' => 15,
            'sales_count' => 8,
            'disk_usage_mb' => 256.50,
        ], [
            'X-Master-Token' => $tenant->api_token,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'active']);

        $this->assertDatabaseHas('tenant_stats', [
            'tenant_id' => $tenant->id,
            'vehicles_count' => 42,
            'leads_count' => 15,
        ]);

        $this->assertNotNull($tenant->fresh()->last_heartbeat_at);
    }

    public function test_heartbeat_returns_suspended_status(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $response = $this->postJson('/api/webhook/heartbeat', [
            'vehicles_count' => 10,
        ], [
            'X-Master-Token' => $tenant->api_token,
        ]);

        $response->assertJson(['status' => 'suspended']);
    }
}
