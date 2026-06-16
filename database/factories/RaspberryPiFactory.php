<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RaspberryPi>
 */
class RaspberryPiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string,mixed>
     */
    public function definition()
    {
        return [
            'raspberry_pi_name' => __('yokakit.raspberry_pi') . ":{$this->faker->unique()->realText(10)}",
            'ip_address' => $this->faker->unique()->ipv4,
        ];
    }
}
