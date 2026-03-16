<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TenantStat;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_active_returns_true_for_active_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $this->assertTrue($tenant->isActive());
    }

    public function test_is_active_returns_false_for_suspended_tenant(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $this->assertFalse($tenant->isActive());
    }

    public function test_is_suspended_returns_true_for_suspended(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $this->assertTrue($tenant->isSuspended());
    }

    public function test_is_suspended_returns_true_for_blocked(): void
    {
        $tenant = Tenant::factory()->blocked()->create();
        $this->assertTrue($tenant->isSuspended());
    }

    public function test_is_suspended_returns_false_for_active(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertFalse($tenant->isSuspended());
    }

    public function test_is_online_returns_true_when_heartbeat_recent(): void
    {
        $tenant = Tenant::factory()->online()->create();
        $this->assertTrue($tenant->isOnline());
    }

    public function test_is_online_returns_false_when_heartbeat_old(): void
    {
        $tenant = Tenant::factory()->offline()->create();
        $this->assertFalse($tenant->isOnline());
    }

    public function test_is_online_returns_false_when_no_heartbeat(): void
    {
        $tenant = Tenant::factory()->create(['last_heartbeat_at' => null]);
        $this->assertFalse($tenant->isOnline());
    }

    public function test_tenant_has_many_stats(): void
    {
        $tenant = Tenant::factory()->create();
        TenantStat::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->assertCount(3, $tenant->stats);
    }

    public function test_tenant_has_latest_stats(): void
    {
        $tenant = Tenant::factory()->create();
        TenantStat::factory()->create(['tenant_id' => $tenant->id, 'vehicles_count' => 10]);
        TenantStat::factory()->create(['tenant_id' => $tenant->id, 'vehicles_count' => 50]);

        $this->assertEquals(50, $tenant->latestStats->vehicles_count);
    }

    public function test_tenant_has_many_payments(): void
    {
        $tenant = Tenant::factory()->create();
        Payment::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $this->assertCount(2, $tenant->payments);
    }
}
