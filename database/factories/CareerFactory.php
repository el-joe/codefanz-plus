<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\EmploymentType;
use App\Models\Career;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Career>
 */
class CareerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employment_type' => EmploymentType::FullTime,
            'is_remote' => false,
            'status' => ContentStatus::Published,
            'closes_at' => null,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft, 'published_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(['closes_at' => now()->subDay()->toDateString()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Career $career) use ($locale, $attributes): void {
            $title = $attributes['title'] ?? fake()->jobTitle();

            $career->translations()->create([
                'locale' => $locale,
                'title' => $title,
                'slug' => Str::slug($title).'-'.$career->id,
                'department' => 'Engineering',
                'location' => 'Cairo',
                'summary' => fake()->sentence(10),
                'responsibilities' => [fake()->sentence(5)],
                'requirements' => [fake()->sentence(5)],
                'body' => fake()->paragraph(),
                ...$attributes,
            ]);
        });
    }
}
