<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductionHistory>
 */
class ProductionHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string,mixed>
     */
    public function definition()
    {
        return [
            'process_name' => $this->faker->unique()->realText(16),
            'part_number_name' => $this->faker->unique()->realText(16),
            'plan_color' => $this->faker->hexColor,
            'cycle_time' => 60.0,
            'over_time' => 120.0,
            'start' => now(),
            'status' => ProductionStatus::RUNNING(),
        ];
    }
}
