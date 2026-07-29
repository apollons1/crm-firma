<?php

namespace Database\Factories;

use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappTemplate>
 */
class WhatsappTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(3),
            'twilio_content_sid' => 'HX'.fake()->regexify('[a-f0-9]{32}'),
            'body' => 'Bună ziua {{1}}, comanda dvs. "{{2}}" a fost confirmată.',
            'variables_count' => 2,
            'category' => fake()->randomElement(['marketing', 'utility', 'authentication']),
            'language' => 'ro',
            'status' => 'approved',
        ];
    }
}
