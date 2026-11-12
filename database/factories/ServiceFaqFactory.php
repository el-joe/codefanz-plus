<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceFaq>
 */
class ServiceFaqFactory extends Factory
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
        return $this->afterCreating(function (ServiceFaq $row) use ($locale, $attributes): void {
            $row->translations()->create([
                'locale' => $locale,
                'question' => fake()->sentence(6).'?',
                'answer' => fake()->paragraph(),
                ...$attributes,
            ]);
        });
    }
}
