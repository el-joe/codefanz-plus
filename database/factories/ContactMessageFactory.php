<?php

namespace Database\Factories;

use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'topic' => ContactTopic::Quote,
            'message' => fake()->paragraph(),
            'preferred_locale' => 'en',
            'locale' => 'en',
            'consented_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Pest',
        ];
    }
}
