<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'amount' => 100.00,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'paid_at' => null,
            'billing_type' => null,
            'asaas_payment_id' => null,
            'invoice_url' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => 'pending',
            'due_date' => now()->subDays(10),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
