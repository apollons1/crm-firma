<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'user_id' => null,
            'contact_id' => null,
            'title' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'estimated_value' => fake()->randomFloat(2, 1000, 100000),
            'currency' => 'RON',
            'status' => fake()->randomElement(['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost']),
            'probability' => fake()->numberBetween(0, 100),
            'expected_close_date' => fake()->dateTimeBetween('now', '+3 months'),
            'lead_source' => fake()->randomElement(['website', 'referral', 'cold_call']),
        ];
    }
}
