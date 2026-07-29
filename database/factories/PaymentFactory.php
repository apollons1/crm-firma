<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'client_id' => null,
            'contact_id' => null,
            'description' => fake()->catchPhrase(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'currency' => 'RON',
            'status' => 'pending',
            'stripe_session_id' => 'cs_test_'.fake()->unique()->uuid(),
            'stripe_payment_intent_id' => null,
            'checkout_url' => fake()->url(),
            'sent_by_user_id' => null,
            'paid_at' => null,
        ];
    }
}
