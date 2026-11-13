<?php

namespace Database\Factories;

use App\Enums\McpClientStatus;
use App\Enums\McpScope;
use App\Enums\Role;
use App\Models\McpClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpClient>
 */
class McpClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'claude-'.fake()->unique()->word(),
            'description' => null,
            'owner_id' => User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor(),
            'status' => McpClientStatus::Active,
            'scopes' => [McpScope::ContentRead->value],
            'allowed_ips' => null,
            'rate_limit_per_minute' => 60,
            'allow_lead_pii' => false,
            'expires_at' => null,
        ];
    }

    /**
     * @param  list<McpScope>  $scopes
     */
    public function scopes(array $scopes): static
    {
        return $this->state(['scopes' => array_map(fn (McpScope $scope): string => $scope->value, $scopes)]);
    }

    public function allScopes(): static
    {
        return $this->state(['scopes' => McpScope::values()]);
    }

    public function disabled(): static
    {
        return $this->state(['status' => McpClientStatus::Disabled]);
    }
}
