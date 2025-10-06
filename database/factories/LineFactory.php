<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Line>
 */
class LineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string,mixed>
     */
    public function definition()
    {
        return [
            'line_name' => __('yokakit.line') . "：{$this->faker->unique()->realText(10)}",
            'chart_color' => $this->faker->hexColor,
            'pin_number' => $this->faker->numberBetween(0, 127),
            'worker_id' => 1,
            'process_id' => 1,
            'raspberry_pi_id' => 1,
        ];
    }
}
