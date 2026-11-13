<?php

namespace Database\Factories;

use App\Enums\RadarBudgetType;
use App\Enums\RadarProjectStatus;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RadarProject>
 */
class RadarProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) fake()->unique()->numberBetween(100000, 999999);

        return [
            'radar_platform_id' => fn (): int => RadarPlatform::query()->firstOrCreate(
                ['key' => RadarPlatform::FREELANCER],
                ['name' => 'Freelancer', 'connector' => 'api', 'status' => 'needs_credentials'],
            )->id,
            'external_id' => $id,
            'url' => 'https://www.freelancer.com/projects/php/project-'.$id,
            'title' => 'Build a Laravel dashboard '.$id,
            'description' => 'We need an experienced developer to build an admin dashboard.',
            'budget_min_minor' => 50000,
            'budget_max_minor' => 100000,
            'currency' => 'USD',
            'budget_type' => RadarBudgetType::Fixed,
            'skills' => ['PHP', 'Laravel'],
            'language' => 'en',
            'posted_at' => now()->subHour(),
            'collected_at' => now(),
            'content_hash' => sha1($id),
            'status' => RadarProjectStatus::New,
            'score' => 70,
        ];
    }
}
