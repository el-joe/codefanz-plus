<?php

namespace Database\Factories;

use App\Enums\ContentIcon;
use App\Enums\ContentStatus;
use App\Enums\PricingModel;
use App\Enums\ProductAvailability;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'icon' => fake()->randomElement(ContentIcon::cases())->value,
            'pricing_models' => [PricingModel::Subscription, PricingModel::CustomQuote],
            'availability' => ProductAvailability::Available,
            'demo_enabled' => true,
            'status' => ContentStatus::Published,
            'sort_order' => 0,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft, 'published_at' => null]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (Product $product) use ($locale, $attributes): void {
            $name = $attributes['name'] ?? ucfirst(fake()->word()).' '.fake()->randomElement(['ERP', 'CRM', 'Desk']);

            $product->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'slug' => Str::slug($name).'-'.$product->id,
                'tagline' => fake()->sentence(6),
                'description' => fake()->paragraph(),
                'features' => [['title' => fake()->words(3, true), 'description' => fake()->sentence()]],
                ...$attributes,
            ]);
        });
    }
}
