<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template' => 'default',
            'status' => PageStatus::Published,
            'published_at' => now(),
        ];
    }

    /**
     * Add an English translation.
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Page $page) use ($locale, $attributes): void {
            $title = $attributes['title'] ?? fake()->sentence(3);

            $page->translations()->create([
                'locale' => $locale,
                'title' => $title,
                'slug' => Str::slug($title).'-'.$page->id,
                'body' => fake()->paragraph(),
                'seo_title' => mb_substr($title, 0, 60),
                'seo_description' => fake()->sentence(12),
                ...$attributes,
            ]);
        });
    }
}
