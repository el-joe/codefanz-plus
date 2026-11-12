<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->settled('paid', ['paid_at' => now()]),
            'customer_id' => fn (array $attributes): int => Payment::query()->findOrFail($attributes['payment_id'])->customer_id,
            'gateway' => fn (array $attributes): string => Payment::query()->findOrFail($attributes['payment_id'])->gateway,
            'amount_minor' => fn (array $attributes): int => Payment::query()->findOrFail($attributes['payment_id'])->amount_minor,
            'currency' => fn (array $attributes): string => Payment::query()->findOrFail($attributes['payment_id'])->currency,
            'reason' => fake()->sentence(),
        ];
    }
}
