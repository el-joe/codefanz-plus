<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issue a draft: take the next gap-free number (locked counter, same transaction), freeze the customer's name,
 * address and tax id, and lock the invoice against further edits.
 */
class IssueInvoice
{
    public function __construct(private AuditLogger $audit, private DocumentNumbers $numbers) {}

    /**
     * @throws ValidationException
     */
    public function handle(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = Invoice::query()->with(['customer', 'items'])->lockForUpdate()->findOrFail($invoice->getKey());

            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => __('This invoice was already issued.')]);
            }

            if ($invoice->items->isEmpty() || $invoice->total_minor <= 0) {
                throw ValidationException::withMessages(['invoice' => __('An invoice needs a positive total before it can be issued.')]);
            }

            $invoice->forceFill([
                'number' => $this->numbers->next(DocumentNumbers::INVOICE),
                'status' => InvoiceStatus::Issued,
                'customer_snapshot' => Invoice::snapshotOf($invoice->customer),
                'issued_at' => now(),
                'issued_by' => $actor->getKey(),
            ])->save();

            $this->audit->record(operation: 'invoice.issued', auditable: $invoice, user: $actor, before: ['status' => InvoiceStatus::Draft->value], after: ['status' => InvoiceStatus::Issued->value, 'number' => $invoice->number, 'total_minor' => $invoice->total_minor, 'currency' => $invoice->currency]);

            return $invoice;
        });
    }
}
