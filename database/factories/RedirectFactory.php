<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_path' => '/en/'.fake()->unique()->slug(3),
            'to_path' => '/en/'.fake()->slug(2),
            'status_code' => 301,
            'is_active' => true,
        ];
    }

    public function gone(): static
    {
        return $this->state(['to_path' => null, 'status_code' => 410]);
    }
}
