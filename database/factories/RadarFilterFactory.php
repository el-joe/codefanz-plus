<?php

namespace Database\Factories;

use App\Models\RadarFilter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RadarFilter>
 */
class RadarFilterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'rules' => ['include_keywords' => ['laravel']],
            'is_active' => true,
            'alerts_enabled' => true,
            'sort_order' => 0,
        ];
    }
}
