<?php

namespace Database\Factories;

use App\Enums\CustomerUserRole;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => 'en',
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withConfirmedTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * A portal user (role "client"). Link it to a customer with CustomerFactory::withPortalUser() or forCustomer().
     */
    public function client(): static
    {
        return $this->role(Role::Client);
    }

    /**
     * A client user with an active membership in the given customer.
     */
    public function forCustomer(Customer $customer, CustomerUserRole $role = CustomerUserRole::Member): static
    {
        return $this->client()->afterCreating(
            fn (User $user) => $customer->users()->attach($user, ['role' => $role, 'invited_at' => now()]),
        );
    }

    /**
     * Assign a role after creation. Requires roles to be seeded.
     */
    public function role(Role $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role->value));
    }
}
