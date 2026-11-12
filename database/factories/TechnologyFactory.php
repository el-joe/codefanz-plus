<?php

namespace Database\Factories;

use App\Enums\TechnologyProficiency;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Technology>
 */
class TechnologyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'technology_category_id' => TechnologyCategory::factory(),
            'key' => fake()->unique()->slug(2),
            'proficiency' => TechnologyProficiency::Supported,
            'is_visible' => true,
            'sort_order' => 0,
        ];
    }

    public function expertise(): static
    {
        return $this->state(['proficiency' => TechnologyProficiency::Expertise]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Technology $technology) use ($locale, $attributes): void {
            $technology->translations()->create(['locale' => $locale, 'name' => ucfirst(fake()->word()), 'description' => fake()->sentence(), ...$attributes]);
        });
    }
}
