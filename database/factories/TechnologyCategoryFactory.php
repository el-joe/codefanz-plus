<?php

namespace Database\Factories;

use App\Models\TechnologyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnologyCategory>
 */
class TechnologyCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'sort_order' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (TechnologyCategory $category) use ($locale, $attributes): void {
            $category->translations()->create(['locale' => $locale, 'name' => fake()->words(2, true), ...$attributes]);
        });
    }
}
