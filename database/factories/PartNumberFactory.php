<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PartNumber>
 */
class PartNumberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string,mixed>
     */
    public function definition()
    {
        return [
            'part_number_name' => __('yokakit.part_number') . "： {$this->faker->unique()->realText(10)}",
            'remark' => $this->faker->realText(256),
        ];
    }
}
