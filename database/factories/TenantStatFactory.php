<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vehicles_count' => fake()->numberBetween(10, 200),
            'leads_count' => fake()->numberBetween(5, 100),
            'sales_count' => fake()->numberBetween(0, 50),
            'disk_usage_mb' => fake()->randomFloat(2, 50, 2000),
        ];
    }
}
