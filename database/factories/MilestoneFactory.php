<?php

namespace Database\Factories;

use App\Enums\MilestoneStatus;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'due_date' => now()->addWeeks(3)->toDateString(),
            'status' => MilestoneStatus::Pending,
            'amount_minor' => 300_000,
            'currency' => 'USD',
            'sort_order' => 1,
        ];
    }

    public function status(MilestoneStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
