<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Void a draft or an unpaid issued invoice. The number stays used (no gaps, no reuse); paid invoices are
 * corrected through refunds instead.
 */
class VoidInvoice
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
            $from = $invoice->status;

            if (! in_array($from, [InvoiceStatus::Draft, InvoiceStatus::Issued], true)) {
                throw ValidationException::withMessages(['reason' => __('Paid or void invoices cannot be voided.')]);
            }

            $invoice->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now(), 'void_reason' => $reason])->save();

            $this->audit->record(operation: 'invoice.voided', auditable: $invoice, user: $actor, before: ['status' => $from->value], after: ['status' => InvoiceStatus::Void->value, 'reason' => $reason]);

            return $invoice;
        });
    }
}
