<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPostVariant>
 */
class SocialPostVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_post_id' => SocialPost::factory(),
            'social_account_id' => SocialAccount::factory(),
            'platform' => fn (array $attributes) => SocialAccount::query()->findOrFail($attributes['social_account_id'])->platform,
            'text' => fake()->sentence(12),
        ];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn (): array => ['social_account_id' => $account->getKey(), 'platform' => $account->platform]);
    }
}
