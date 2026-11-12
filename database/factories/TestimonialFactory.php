<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'company' => fake()->company(),
            'rating' => null,
            'consent_confirmed' => true,
            'consent_confirmed_at' => now(),
            'status' => ContentStatus::Published,
            'is_featured' => false,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft, 'published_at' => null]);
    }

    public function withoutConsent(): static
    {
        return $this->state(['consent_confirmed' => false, 'consent_confirmed_at' => null]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Testimonial $testimonial) use ($locale, $attributes): void {
            $testimonial->translations()->create([
                'locale' => $locale,
                'quote' => fake()->sentence(14),
                'author_role' => fake()->jobTitle(),
                ...$attributes,
            ]);
        });
    }
}
