<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edit a draft: line description, tax rate, due date and notes. Issued invoices never change.
 */
class UpdateDraftInvoice
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{description: string, tax_rate_bp: int, due_at: string|null, notes: string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(Invoice $invoice, array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $actor): Invoice {
            $invoice = Invoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->getKey());

            if ($invoice->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => __('Only draft invoices can be edited.')]);
            }

            $before = $invoice->only(['tax_rate_bp', 'due_at', 'notes', 'total_minor']);

            $invoice->items->first()?->update(['description' => $data['description']]);
            $invoice->fill(['tax_rate_bp' => $data['tax_rate_bp'], 'due_at' => $data['due_at'], 'notes' => $data['notes']]);
            $invoice->recalculate();
            $invoice->save();

            $this->audit->record(operation: 'invoice.updated', auditable: $invoice, user: $actor, before: $before, after: $invoice->only(['tax_rate_bp', 'due_at', 'notes', 'total_minor']));

            return $invoice;
        });
    }
}
