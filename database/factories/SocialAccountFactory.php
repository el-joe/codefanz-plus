<?php

namespace Database\Factories;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * A manual account by default; use connected() for one with API tokens.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => SocialPlatform::LinkedinPage,
            'name' => fake()->company(),
            'status' => SocialAccountStatus::ManualMode,
        ];
    }

    public function platform(SocialPlatform $platform): static
    {
        return $this->state(fn (): array => ['platform' => $platform]);
    }

    public function connected(SocialPlatform $platform = SocialPlatform::LinkedinPage): static
    {
        return $this->state(fn (): array => [
            'platform' => $platform,
            'status' => SocialAccountStatus::Connected,
            'external_id' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'access_token' => 'test-access-'.fake()->lexify('??????????'),
            'refresh_token' => $platform === SocialPlatform::FacebookPage || $platform === SocialPlatform::InstagramBusiness ? null : 'test-refresh-'.fake()->lexify('??????????'),
            'token_expires_at' => $platform === SocialPlatform::FacebookPage || $platform === SocialPlatform::InstagramBusiness ? null : now()->addDays(30),
            'meta' => $platform === SocialPlatform::InstagramBusiness ? ['page_id' => '5550001'] : null,
            'last_verified_at' => now(),
        ]);
    }
}
