<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'subject' => fake()->sentence(5),
            'category' => TicketCategory::Bug,
            'priority' => TicketPriority::Normal,
            'status' => TicketStatus::Open,
            'last_activity_at' => now(),
        ];
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
