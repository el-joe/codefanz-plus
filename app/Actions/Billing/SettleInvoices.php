<?php

namespace App\Actions\Billing;

use App\Enums\ActorType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Payment;
use App\Services\AuditLogger;

/**
 * After a verified payment: issued invoices for its payment link are paid; issued invoices for its milestone are
 * paid once the milestone has no remaining balance. Runs inside VerifyPayment's transaction.
 */
class SettleInvoices
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Payment $payment): void
    {
        $milestoneSettled = $payment->milestone_id !== null
            && Milestone::query()->whereKey($payment->milestone_id)->whereColumn('paid_minor', '>=', 'amount_minor')->exists();

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->where(fn ($query) => $query
                ->where('payment_link_id', $payment->payment_link_id)
                ->when($milestoneSettled, fn ($query) => $query->orWhere(fn ($query) => $query->where('milestone_id', $payment->milestone_id)->whereNull('payment_link_id'))))
            ->lockForUpdate()
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => now()])->save();

            $this->audit->record(operation: 'invoice.paid', auditable: $invoice, actorType: ActorType::System, actorLabel: 'Payment verification', before: ['status' => InvoiceStatus::Issued->value], after: ['status' => InvoiceStatus::Paid->value, 'payment' => $payment->reference]);
        }
    }
}
