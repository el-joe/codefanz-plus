<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
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

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Faq $faq) use ($locale, $attributes): void {
            $faq->translations()->create([
                'locale' => $locale,
                'question' => rtrim(fake()->sentence(6), '.').'?',
                'answer' => fake()->paragraph(),
                ...$attributes,
            ]);
        });
    }
}
