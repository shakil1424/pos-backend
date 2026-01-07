<?php

namespace Database\Factories;

use App\Models\DailySalesSummary;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailySalesSummaryFactory extends Factory
{
    protected $model = DailySalesSummary::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'date' => $this->faker->date(),
            'total_orders' => $this->faker->numberBetween(1, 100),
            'total_sales' => $this->faker->randomFloat(2, 100, 10000),
            'average_order_value' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}