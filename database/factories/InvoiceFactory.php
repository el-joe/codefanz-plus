<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Draft invoices with one line. Issue them with App\Actions\Billing\IssueInvoice in tests.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'currency' => 'USD',
            'subtotal_minor' => 100_000,
            'total_minor' => 100_000,
            'due_at' => now()->addDays(14)->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice): void {
            $invoice->items()->create(['description' => 'Milestone delivery', 'quantity' => 1, 'unit_minor' => $invoice->subtotal_minor, 'total_minor' => $invoice->subtotal_minor, 'sort_order' => 1]);
        });
    }
}
