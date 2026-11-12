<?php

namespace Database\Factories;

use App\Enums\BudgetRange;
use App\Enums\ContactMethod;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\ProjectTimeline;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => LeadSource::QuoteForm,
            'status' => LeadStatus::New,
            'priority' => LeadPriority::Normal,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'company' => fake()->company(),
            'country' => 'EG',
            'preferred_locale' => 'en',
            'preferred_contact' => ContactMethod::Email,
            'platforms' => ['web'],
            'description' => fake()->paragraph(),
            'budget_range' => BudgetRange::From10kTo25k,
            'timeline' => ProjectTimeline::OneToThreeMonths,
            'consent_at' => now(),
            'locale' => 'en',
        ];
    }

    public function status(LeadStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'lost_reason' => $status === LeadStatus::Lost ? 'Chose another vendor' : null,
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => ['assigned_to' => $user->getKey()]);
    }

    public function source(LeadSource $source): static
    {
        return $this->state(fn (): array => ['source' => $source]);
    }
}
