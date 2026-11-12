<?php

namespace Database\Factories;

use App\Enums\ContentIcon;
use App\Enums\ContentStatus;
use App\Enums\ServiceTier;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tier' => ServiceTier::Core,
            'icon' => fake()->randomElement(ContentIcon::cases())->value,
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

    public function specialist(): static
    {
        return $this->state(['tier' => ServiceTier::Specialist]);
    }

    /**
     * Add a translation for the locale.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Service $service) use ($locale, $attributes): void {
            $title = $attributes['title'] ?? fake()->words(3, true);

            $service->translations()->create([
                'locale' => $locale,
                'title' => $title,
                'slug' => Str::slug($title).'-'.$service->id,
                'summary' => fake()->sentence(10),
                'body' => fake()->paragraph(),
                ...$attributes,
            ]);
        });
    }
}
