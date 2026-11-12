<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\PaymentLink;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Draft invoices generated from a milestone (its full amount) or a payment link (the requested amount). The draft
 * has no number until it is issued.
 */
class CreateInvoice
{
    public function __construct(private AuditLogger $audit) {}

    public function fromMilestone(Milestone $milestone, User $actor): Invoice
    {
        $milestone->loadMissing('project');

        return $this->create([
            'customer_id' => $milestone->project->customer_id,
            'project_id' => $milestone->project_id,
            'milestone_id' => $milestone->getKey(),
            'currency' => $milestone->currency,
        ], $milestone->project->name.' · '.$milestone->title, $milestone->amount_minor, $actor);
    }

    public function fromPaymentLink(PaymentLink $link, User $actor): Invoice
    {
        $link->loadMissing(['project', 'milestone']);

        $description = $link->description ?: ($link->milestone ? $link->project->name.' · '.$link->milestone->title : $link->project->name);

        return $this->create([
            'customer_id' => $link->customer_id,
            'project_id' => $link->project_id,
            'milestone_id' => $link->milestone_id,
            'payment_link_id' => $link->getKey(),
            'currency' => $link->currency,
        ], $description, $link->amount_minor, $actor);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(array $attributes, string $description, int $amountMinor, User $actor): Invoice
    {
        return DB::transaction(function () use ($attributes, $description, $amountMinor, $actor): Invoice {
            $invoice = Invoice::create([
                ...$attributes,
                'due_at' => now()->addDays((int) config('codefanz.payments.invoice_due_days', 14))->toDateString(),
                'created_by' => $actor->getKey(),
            ]);

            $invoice->items()->create([
                'description' => mb_substr($description, 0, 255),
                'quantity' => 1,
                'unit_minor' => $amountMinor,
                'total_minor' => $amountMinor,
                'sort_order' => 1,
            ]);

            $invoice->load('items')->recalculate();
            $invoice->save();

            $this->audit->record(operation: 'invoice.drafted', auditable: $invoice, user: $actor, after: ['total_minor' => $invoice->total_minor, 'currency' => $invoice->currency]);

            return $invoice;
        });
    }
}
