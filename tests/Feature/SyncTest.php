<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_requires_auth(): void
    {
        $this->getJson(route('sync.tenants'))->assertStatus(401);
    }

    public function test_sync_returns_tenant_data(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('health')->andReturn(['success' => true, 'data' => ['status' => 'ok']]);
            $mock->shouldReceive('stats')->andReturn([
                'success' => true,
                'data' => [
                    'vehicles_count' => 25,
                    'leads_count' => 10,
                    'sales_count' => 3,
                    'disk_usage_mb' => 100,
                ],
            ]);
        });

        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($user)->getJson(route('sync.tenants'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'tenants' => [['id', 'name', 'online', 'stats']],
            'totals' => ['vehicles', 'leads', 'sales', 'active', 'suspended', 'online', 'overdue_payments'],
        ]);

        $this->assertDatabaseHas('tenant_stats', [
            'tenant_id' => $tenant->id,
            'vehicles_count' => 25,
        ]);
    }

    public function test_sync_handles_offline_tenant(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('health')->andReturn(['success' => false, 'error' => 'timeout']);
            $mock->shouldReceive('stats')->andReturn(['success' => false, 'data' => null, 'error' => 'timeout']);
        });

        $user = User::factory()->create();
        Tenant::factory()->create();

        $response = $this->actingAs($user)->getJson(route('sync.tenants'));

        $response->assertStatus(200);
        $response->assertJsonPath('tenants.0.online', false);
        $response->assertJsonPath('tenants.0.error', 'timeout');
    }
}
