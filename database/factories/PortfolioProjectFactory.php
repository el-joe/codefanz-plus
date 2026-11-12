<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Models\PortfolioProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortfolioProject>
 */
class PortfolioProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(ProjectType::cases()),
            'client_name' => fake()->company(),
            'is_client_public' => false,
            'project_date' => fake()->dateTimeBetween('-3 years')->format('Y-m-d'),
            'is_featured' => false,
            'visibility' => ProjectVisibility::Public,
            'status' => ContentStatus::Published,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft, 'published_at' => null]);
    }

    public function unlisted(): static
    {
        return $this->state(['visibility' => ProjectVisibility::Unlisted]);
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
        return $this->afterCreating(function (PortfolioProject $project) use ($locale, $attributes): void {
            $title = $attributes['title'] ?? fake()->words(3, true);

            $project->translations()->create([
                'locale' => $locale,
                'title' => $title,
                'slug' => Str::slug($title).'-'.$project->id,
                'summary' => fake()->sentence(10),
                'challenge' => fake()->paragraph(),
                'solution' => fake()->paragraph(),
                'results' => fake()->paragraph(),
                ...$attributes,
            ]);
        });
    }
}
