<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlannedOutage>
 */
class PlannedOutageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string,mixed>
     */
    public function definition()
    {
        return [
            'planned_outage_name' => $this->faker->unique()->realText(16),
            'start_time' => '12:00',
            'end_time' => '13:00',
        ];
    }
}
