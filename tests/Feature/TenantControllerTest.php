<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AsaasService;
use App\Services\TenantApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('tenants.index'))->assertRedirect('/login');
    }

    public function test_index_lists_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('tenants.index'));
        $response->assertStatus(200);
        $response->assertViewHas('tenants');
    }

    public function test_create_shows_form(): void
    {
        $response = $this->actingAs($this->user)->get(route('tenants.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_tenant(): void
    {
        $data = [
            'name' => 'Loja Teste',
            'domain' => 'http://loja-teste.com',
            'monthly_amount' => '100,00',
            'owner_name' => 'João Silva',
            'owner_email' => 'joao@teste.com',
            'owner_phone' => '(28) 99999-0000',
            'owner_cpf_cnpj' => '123.456.789-00',
        ];

        $response = $this->actingAs($this->user)->post(route('tenants.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'name' => 'Loja Teste',
            'domain' => 'http://loja-teste.com',
            'monthly_amount' => '100.00',
            'owner_email' => 'joao@teste.com',
        ]);
    }

    public function test_store_does_not_create_asaas_subscription(): void
    {
        $data = [
            'name' => 'Loja Teste',
            'domain' => 'http://loja-teste.com',
            'monthly_amount' => '100,00',
            'owner_name' => 'João Silva',
            'owner_email' => 'joao@teste.com',
            'owner_phone' => '(28) 99999-0000',
            'owner_cpf_cnpj' => '123.456.789-00',
        ];

        $this->actingAs($this->user)->post(route('tenants.store'), $data);

        $tenant = Tenant::first();
        $this->assertNull($tenant->asaas_customer_id);
        $this->assertNull($tenant->asaas_subscription_id);
    }

    public function test_store_generates_api_token(): void
    {
        $data = [
            'name' => 'Loja Token',
            'domain' => 'http://loja-token.com',
            'monthly_amount' => '100,00',
            'owner_name' => 'Maria',
            'owner_email' => 'maria@teste.com',
            'owner_phone' => '(28) 99999-1111',
            'owner_cpf_cnpj' => '111.222.333-44',
        ];

        $this->actingAs($this->user)->post(route('tenants.store'), $data);

        $tenant = Tenant::first();
        $this->assertNotNull($tenant->api_token);
        $this->assertEquals(64, strlen($tenant->api_token));
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('tenants.store'), []);

        $response->assertSessionHasErrors(['name', 'domain', 'monthly_amount', 'owner_name', 'owner_email', 'owner_phone', 'owner_cpf_cnpj']);
    }

    public function test_store_validates_unique_domain(): void
    {
        Tenant::factory()->create(['domain' => 'http://existing.com']);

        $data = [
            'name' => 'Duplicado',
            'domain' => 'http://existing.com',
            'monthly_amount' => '100,00',
            'owner_name' => 'Teste',
            'owner_email' => 'dup@teste.com',
            'owner_phone' => '(28) 99999-0000',
            'owner_cpf_cnpj' => '123.456.789-00',
        ];

        $response = $this->actingAs($this->user)->post(route('tenants.store'), $data);
        $response->assertSessionHasErrors('domain');
    }

    public function test_show_displays_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)->get(route('tenants.show', $tenant));
        $response->assertStatus(200);
        $response->assertSee($tenant->name);
    }

    public function test_edit_shows_form(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)->get(route('tenants.edit', $tenant));
        $response->assertStatus(200);
    }

    public function test_update_modifies_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $data = [
            'name' => 'Nome Atualizado',
            'domain' => $tenant->domain,
            'status' => 'active',
            'monthly_amount' => '150,00',
            'owner_name' => $tenant->owner_name,
            'owner_email' => $tenant->owner_email,
            'owner_phone' => $tenant->owner_phone,
            'owner_cpf_cnpj' => $tenant->owner_cpf_cnpj,
        ];

        $response = $this->actingAs($this->user)->put(route('tenants.update', $tenant), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Nome Atualizado',
            'monthly_amount' => '150.00',
        ]);
    }

    public function test_suspend_changes_status(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('suspend')->andReturn(['success' => true]);
        });

        $tenant = Tenant::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->user)->post(route('tenants.suspend', $tenant));

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
    }

    public function test_suspend_works_even_without_tenant_communication(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('suspend')->andReturn(['success' => false, 'error' => 'timeout']);
        });

        $tenant = Tenant::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->user)->post(route('tenants.suspend', $tenant));

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended']);
    }

    public function test_reactivate_changes_status(): void
    {
        $this->mock(TenantApiService::class, function ($mock) {
            $mock->shouldReceive('reactivate')->andReturn(['success' => true]);
        });

        $tenant = Tenant::factory()->suspended()->create();

        $response = $this->actingAs($this->user)->post(route('tenants.reactivate', $tenant));

        $response->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
    }

    public function test_regenerate_token_creates_new_token(): void
    {
        $tenant = Tenant::factory()->create();
        $oldToken = $tenant->api_token;

        $response = $this->actingAs($this->user)->post(route('tenants.regenerate-token', $tenant));

        $response->assertRedirect();
        $this->assertNotEquals($oldToken, $tenant->fresh()->api_token);
    }

    public function test_activate_billing_requires_date(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)->post(route('tenants.activate-billing', $tenant), []);
        $response->assertSessionHasErrors('first_due_date');
    }

    public function test_activate_billing_rejects_past_date(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)->post(route('tenants.activate-billing', $tenant), [
            'first_due_date' => '2020-01-01',
        ]);
        $response->assertSessionHasErrors('first_due_date');
    }

    public function test_activate_billing_rejects_if_already_active(): void
    {
        $tenant = Tenant::factory()->withAsaas()->create();

        $response = $this->actingAs($this->user)->post(route('tenants.activate-billing', $tenant), [
            'first_due_date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $response->assertSessionHas('error');
    }

    public function test_activate_billing_fails_without_api_key(): void
    {
        config(['services.asaas.api_key' => '']);

        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)->post(route('tenants.activate-billing', $tenant), [
            'first_due_date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $response->assertSessionHas('error');
    }

    public function test_cancel_billing_clears_subscription_id(): void
    {
        $this->mock(AsaasService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscription')->andReturn(['deleted' => true]);
        });

        $tenant = Tenant::factory()->withAsaas()->create();

        $response = $this->actingAs($this->user)->post(route('tenants.cancel-billing', $tenant));

        $response->assertRedirect();
        $this->assertNull($tenant->fresh()->asaas_subscription_id);
    }

    public function test_cancel_billing_fails_without_subscription(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->user)->post(route('tenants.cancel-billing', $tenant));
        $response->assertSessionHas('error');
    }

    public function test_store_parses_brazilian_money_format(): void
    {
        $data = [
            'name' => 'Loja Money',
            'domain' => 'http://loja-money.com',
            'monthly_amount' => '1.250,99',
            'owner_name' => 'Teste',
            'owner_email' => 'money@teste.com',
            'owner_phone' => '(28) 99999-0000',
            'owner_cpf_cnpj' => '123.456.789-00',
        ];

        $this->actingAs($this->user)->post(route('tenants.store'), $data);

        $this->assertDatabaseHas('tenants', ['monthly_amount' => '1250.99']);
    }
}
