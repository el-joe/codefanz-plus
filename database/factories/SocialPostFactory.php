<?php

namespace Database\Factories;

use App\Enums\PostOrigin;
use App\Enums\SocialPostStatus;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'locale' => 'en',
            'status' => SocialPostStatus::Draft,
            'origin' => PostOrigin::Human,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => ['status' => SocialPostStatus::InReview, 'submitted_at' => now()]);
    }

    public function approved(?User $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => SocialPostStatus::Approved,
            'reviewed_by' => $reviewer?->getKey() ?? User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function scheduled(?\DateTimeInterface $at = null, ?User $reviewer = null): static
    {
        return $this->approved($reviewer)->state(fn (): array => [
            'status' => SocialPostStatus::Scheduled,
            'scheduled_at' => $at ?? now()->subMinute(),
        ]);
    }

    public function aiOrigin(?User $generator = null): static
    {
        return $this->state(fn (): array => ['origin' => PostOrigin::Ai, 'generated_by' => $generator?->getKey()]);
    }
}
