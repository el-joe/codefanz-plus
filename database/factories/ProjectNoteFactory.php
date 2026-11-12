<?php

namespace Database\Factories;

use App\Enums\ClientVisibility;
use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectNote>
 */
class ProjectNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'visibility' => ClientVisibility::Client,
            'body' => fake()->sentence(),
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (): array => ['visibility' => ClientVisibility::Internal]);
    }
}
