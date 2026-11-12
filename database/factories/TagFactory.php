<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Tag $tag) use ($locale, $attributes): void {
            $name = $attributes['name'] ?? fake()->word();

            $tag->translations()->create(['locale' => $locale, 'name' => $name, 'slug' => Str::slug($name).'-'.$tag->id, ...$attributes]);
        });
    }
}
