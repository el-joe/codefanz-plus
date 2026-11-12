<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->paragraph(),
            'type' => ProjectType::Web,
            'status' => ProjectStatus::InProgress,
            'start_date' => now()->subMonth()->toDateString(),
            'due_date' => now()->addMonths(2)->toDateString(),
            'budget_minor' => 1_200_000,
            'currency' => 'USD',
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer->getKey()]);
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
