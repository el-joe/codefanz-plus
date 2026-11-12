<?php

namespace Database\Factories;

use App\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostCategory>
 */
class PostCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sort_order' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (PostCategory $category) use ($locale, $attributes): void {
            $name = $attributes['name'] ?? fake()->words(2, true);

            $category->translations()->create(['locale' => $locale, 'name' => $name, 'slug' => Str::slug($name).'-'.$category->id, ...$attributes]);
        });
    }
}
