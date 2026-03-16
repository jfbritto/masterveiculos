<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewHas(['tenants', 'totalVehicles', 'totalLeads', 'activeTenants']);
    }

    public function test_dashboard_shows_correct_stats(): void
    {
        $user = User::factory()->create();

        $tenant1 = Tenant::factory()->create(['status' => 'active']);
        $tenant2 = Tenant::factory()->suspended()->create();

        TenantStat::factory()->create(['tenant_id' => $tenant1->id, 'vehicles_count' => 30, 'leads_count' => 10, 'sales_count' => 5]);
        TenantStat::factory()->create(['tenant_id' => $tenant2->id, 'vehicles_count' => 20, 'leads_count' => 8, 'sales_count' => 3]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertViewHas('totalVehicles', 50);
        $response->assertViewHas('totalLeads', 18);
        $response->assertViewHas('totalSales', 8);
        $response->assertViewHas('activeTenants', 1);
        $response->assertViewHas('suspendedTenants', 1);
    }

    public function test_dashboard_counts_overdue_payments(): void
    {
        $user = User::factory()->create();

        Payment::factory()->overdue()->create();
        Payment::factory()->overdue()->create();
        Payment::factory()->paid()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertViewHas('overduePayments', 2);
    }
}
