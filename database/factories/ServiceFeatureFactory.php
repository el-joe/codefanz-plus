<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceFeature>
 */
class ServiceFeatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'sort_order' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (ServiceFeature $row) use ($locale, $attributes): void {
            $row->translations()->create([
                'locale' => $locale,
                'title' => fake()->words(3, true),
                'description' => fake()->sentence(8),
                ...$attributes,
            ]);
        });
    }
}
