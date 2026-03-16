<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_paid_returns_true_for_confirmed(): void
    {
        $payment = Payment::factory()->create(['status' => 'confirmed']);
        $this->assertTrue($payment->isPaid());
    }

    public function test_is_paid_returns_true_for_received(): void
    {
        $payment = Payment::factory()->create(['status' => 'received']);
        $this->assertTrue($payment->isPaid());
    }

    public function test_is_paid_returns_false_for_pending(): void
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $this->assertFalse($payment->isPaid());
    }

    public function test_is_overdue_returns_true_for_overdue_status(): void
    {
        $payment = Payment::factory()->create(['status' => 'overdue']);
        $this->assertTrue($payment->isOverdue());
    }

    public function test_is_overdue_returns_true_for_pending_past_due(): void
    {
        $payment = Payment::factory()->overdue()->create();
        $this->assertTrue($payment->isOverdue());
    }

    public function test_is_overdue_returns_false_for_pending_future_due(): void
    {
        $payment = Payment::factory()->create([
            'status' => 'pending',
            'due_date' => now()->addDays(10),
        ]);
        $this->assertFalse($payment->isOverdue());
    }

    public function test_payment_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, $payment->tenant->id);
    }
}
