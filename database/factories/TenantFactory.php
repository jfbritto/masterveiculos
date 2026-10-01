<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domain' => 'http://' . fake()->unique()->domainName(),
            'api_token' => Str::random(64),
            'status' => 'active',
            'monthly_amount' => 100.00,
            'owner_name' => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_phone' => '(28) 99999-' . fake()->numerify('####'),
            'owner_cpf_cnpj' => fake()->numerify('###.###.###-##'),
            'notes' => null,
            'last_heartbeat_at' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    public function blocked(): static
    {
        return $this->state(['status' => 'blocked']);
    }

    public function online(): static
    {
        return $this->state(['last_heartbeat_at' => now()]);
    }

    public function offline(): static
    {
        return $this->state(['last_heartbeat_at' => now()->subHours(3)]);
    }

    public function withAsaas(): static
    {
        return $this->state([
            'asaas_customer_id' => 'cus_' . Str::random(16),
            'asaas_subscription_id' => 'sub_' . Str::random(16),
        ]);
    }
}
