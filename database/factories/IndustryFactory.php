<?php

namespace Database\Factories;

use App\Enums\ContentIcon;
use App\Enums\ContentStatus;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Industry>
 */
class IndustryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'icon' => fake()->randomElement(ContentIcon::cases())->value,
            'status' => ContentStatus::Published,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft, 'published_at' => null]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Industry $industry) use ($locale, $attributes): void {
            $name = $attributes['name'] ?? fake()->words(2, true);

            $industry->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'slug' => Str::slug($name).'-'.$industry->id,
                'summary' => fake()->sentence(12),
                'typical_solutions' => fake()->words(3),
                ...$attributes,
            ]);
        });
    }
}
