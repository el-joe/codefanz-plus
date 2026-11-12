<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Pending payments by default. Settled states are set with forceFill in tests (status is not mass assignable
 * and changes in the app only through the payment actions).
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_link_id' => PaymentLink::factory(),
            'customer_id' => fn (array $attributes): int => PaymentLink::query()->findOrFail($attributes['payment_link_id'])->customer_id,
            'project_id' => fn (array $attributes): ?int => PaymentLink::query()->findOrFail($attributes['payment_link_id'])->project_id,
            'milestone_id' => fn (array $attributes): ?int => PaymentLink::query()->findOrFail($attributes['payment_link_id'])->milestone_id,
            'gateway' => 'stripe',
            'amount_minor' => fn (array $attributes): int => PaymentLink::query()->findOrFail($attributes['payment_link_id'])->amount_minor,
            'currency' => fn (array $attributes): string => PaymentLink::query()->findOrFail($attributes['payment_link_id'])->currency,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Payment $payment): void {
            $payment->gateway_payment_id ??= 'cs_test_'.Str::random(24);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function settled(string $status, array $attributes = []): static
    {
        return $this->afterCreating(function (Payment $payment) use ($status, $attributes): void {
            Payment::query()->whereKey($payment->getKey())->update(['status' => $status, ...$attributes]);
            $payment->refresh();
        });
    }
}
