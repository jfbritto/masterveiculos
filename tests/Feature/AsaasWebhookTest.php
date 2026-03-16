<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\TenantApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_invalid_payload(): void
    {
        $response = $this->postJson('/api/webhook/asaas', []);
        $response->assertStatus(400);
    }

    public function test_payment_created_stores_payment(): void
    {
        $tenant = Tenant::factory()->withAsaas()->create();

        $response = $this->postJson('/api/webhook/asaas', [
            'event' => 'PAYMENT_CREATED',
            'payment' => [
                'id' => 'pay_123',
                'value' => 100.00,
                'dueDate' => '2026-04-15',
                'billingType' => 'PIX',
                'invoiceUrl' => 'https://asaas.com/invoice/123',
                'externalReference' => 'tenant_' . $tenant->id,
                'subscription' => $tenant->asaas_subscription_id,
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_123',
            'amount' => '100.00',
            'status' => 'pending',
        ]);
    }

    public function test_payment_confirmed_updates_status(): void
    {
        $tenant = Tenant::factory()->withAsaas()->create();
        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_456',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/webhook/asaas', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_456',
                'paymentDate' => '2026-04-10',
                'externalReference' => 'tenant_' . $tenant->id,
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'asaas_payment_id' => 'pay_456',
            'status' => 'confirmed',
        ]);
    }

    public function test_payment_confirmed_reactivates_suspended_tenant(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('reactivate')->once()->andReturn(['success' => true]);
        });

        $tenant = Tenant::factory()->suspended()->withAsaas()->create();
        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_789',
            'status' => 'pending',
        ]);

        $this->postJson('/api/webhook/asaas', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_789',
                'paymentDate' => '2026-04-10',
                'externalReference' => 'tenant_' . $tenant->id,
            ],
        ]);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
    }

    public function test_payment_overdue_suspends_tenant_after_5_days(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('suspend')->once()->andReturn(['success' => true]);
        });

        $tenant = Tenant::factory()->create(['status' => 'active']);
        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_overdue',
        ]);

        $this->postJson('/api/webhook/asaas', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_overdue',
                'dueDate' => now()->subDays(6)->format('Y-m-d'),
                'externalReference' => 'tenant_' . $tenant->id,
            ],
        ]);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
    }

    public function test_payment_overdue_blocks_tenant_after_15_days(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('suspend')->once()->andReturn(['success' => true]);
        });

        $tenant = Tenant::factory()->suspended()->create();
        Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_block',
        ]);

        $this->postJson('/api/webhook/asaas', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_block',
                'dueDate' => now()->subDays(16)->format('Y-m-d'),
                'externalReference' => 'tenant_' . $tenant->id,
            ],
        ]);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'blocked']);
    }

    public function test_payment_refunded_updates_status(): void
    {
        $tenant = Tenant::factory()->withAsaas()->create();
        Payment::factory()->paid()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_refund',
        ]);

        $this->postJson('/api/webhook/asaas', [
            'event' => 'PAYMENT_REFUNDED',
            'payment' => [
                'id' => 'pay_refund',
                'externalReference' => 'tenant_' . $tenant->id,
            ],
        ]);

        $this->assertDatabaseHas('payments', [
            'asaas_payment_id' => 'pay_refund',
            'status' => 'refunded',
        ]);
    }

    public function test_finds_tenant_by_subscription_id(): void
    {
        $tenant = Tenant::factory()->withAsaas()->create();

        $this->postJson('/api/webhook/asaas', [
            'event' => 'PAYMENT_CREATED',
            'payment' => [
                'id' => 'pay_sub_lookup',
                'value' => 100,
                'dueDate' => '2026-05-01',
                'subscription' => $tenant->asaas_subscription_id,
            ],
        ]);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_sub_lookup',
        ]);
    }
}
