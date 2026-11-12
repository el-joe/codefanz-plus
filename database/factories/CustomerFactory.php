<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\CustomerUserRole;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CustomerType::Company,
            'status' => CustomerStatus::Active,
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'country' => 'EG',
            'preferred_locale' => 'en',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => CustomerStatus::Inactive]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CustomerType::Individual,
            'company_name' => $attributes['contact_name'] ?? fake()->name(),
        ]);
    }

    /**
     * Link a new client user (role "client") to the customer after creation.
     */
    public function withPortalUser(CustomerUserRole $role = CustomerUserRole::Owner, ?User $user = null): static
    {
        return $this->afterCreating(function (Customer $customer) use ($role, $user): void {
            $user ??= User::factory()->client()->create();
            $customer->users()->attach($user, ['role' => $role, 'invited_at' => now()]);
        });
    }
}
