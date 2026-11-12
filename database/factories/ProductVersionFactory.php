<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVersion>
 */
class ProductVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => fake()->unique()->numerify('#.#.#'),
            'released_at' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withTranslation(string $locale = 'en', array $attributes = []): static
    {
        return $this->afterCreating(function (ProductVersion $version) use ($locale, $attributes): void {
            $version->translations()->create(['locale' => $locale, 'changelog' => '- '.fake()->sentence(), ...$attributes]);
        });
    }
}
