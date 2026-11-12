<?php

namespace Database\Factories;

use App\Enums\PaymentLinkStatus;
use App\Models\Milestone;
use App\Models\PaymentLink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentLink>
 */
class PaymentLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'customer_id' => fn (array $attributes): int => Project::query()->findOrFail($attributes['project_id'])->customer_id,
            'amount_minor' => 150_000,
            'currency' => 'USD',
            'description' => fake()->sentence(4),
            'expires_at' => now()->addDays(14),
            'single_use' => true,
            'gateways' => ['stripe'],
            'status' => PaymentLinkStatus::Active,
        ];
    }

    public function forMilestone(Milestone $milestone): static
    {
        return $this->state(fn (): array => [
            'project_id' => $milestone->project_id,
            'milestone_id' => $milestone->getKey(),
            'currency' => $milestone->currency,
        ]);
    }

    public function status(PaymentLinkStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
