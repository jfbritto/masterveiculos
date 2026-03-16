<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_lists_payments(): void
    {
        Payment::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('payments.index'));
        $response->assertStatus(200);
    }

    public function test_create_shows_form_with_tenants(): void
    {
        Tenant::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->get(route('payments.create'));
        $response->assertStatus(200);
    }

    public function test_store_creates_payment(): void
    {
        $tenant = Tenant::factory()->create();

        $data = [
            'tenant_id' => $tenant->id,
            'amount' => 100,
            'due_date' => now()->addMonth()->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->user)->post(route('payments.store'), $data);

        $response->assertRedirect(route('payments.index'));
        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'amount' => '100.00',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->post(route('payments.store'), []);
        $response->assertSessionHasErrors(['tenant_id', 'amount', 'due_date']);
    }

    public function test_mark_paid_updates_payment(): void
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->user)->post(route('payments.mark-paid', $payment));

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'confirmed',
        ]);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_cancel_updates_payment(): void
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->user)->post(route('payments.cancel', $payment));

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'cancelled',
        ]);
    }
}
