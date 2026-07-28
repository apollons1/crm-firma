<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'cui' => fake()->numerify('RO########'),
            'address' => fake()->address(),
            'industry' => fake()->word(),
            'website' => fake()->url(),
            'employees_count' => fake()->numberBetween(1, 500),
            'notes' => fake()->sentence(),
            'status' => fake()->randomElement(['prospect', 'active', 'inactive']),
        ];
    }
}
