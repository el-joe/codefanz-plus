<?php

namespace App\Actions\Refunds;

use App\Actions\Payments\RecomputeMilestonePaid;
use App\Enums\ActorType;
use App\Enums\PaymentEventSource;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\RefundStatusChanged;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use App\Services\DocumentNumbers;
use App\Services\SellerDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

/**
 * The only place a refund reaches the ledger: once the gateway confirmed it (API response or a refund webhook
 * re-checked through the API) or finance recorded a manual refund's external reference. In one transaction
 * holding row locks on the payment and the refund: payments.refunded_minor and status (Refunded /
 * PartiallyRefunded via transitionTo + payment_events), a gap-free credit note CN-YYYY-NNNNN, the milestone's
 * paid amount recomputed and an audit entry. Calling it again for a succeeded refund is a no-op.
 */
class CompleteRefund
{
    public function __construct(
        private AuditLogger $audit,
        private RecomputeMilestonePaid $recomputeMilestone,
        private DocumentNumbers $numbers,
        private SellerDetails $seller,
        private DeliveryRecipients $recipients,
    ) {}

    public function handle(Refund $refund, ?User $actor, ?string $gatewayRefundId = null, ?string $manualReference = null, ?WebhookEvent $webhookEvent = null): Refund
    {
        $completed = false;

        $refund = DB::transaction(function () use ($refund, $actor, $gatewayRefundId, $manualReference, $webhookEvent, &$completed): Refund {
            $payment = Payment::query()->with('customer')->lockForUpdate()->findOrFail($refund->payment_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($locked->status === RefundStatus::Succeeded) {
                return $locked;
            }

            if (! in_array($locked->status, [RefundStatus::Processing, RefundStatus::ManualRequired], true)) {
                throw new LogicException("Refund {$locked->reference} cannot complete from {$locked->status->value}.");
            }

            $refunded = $payment->refunded_minor + $locked->amount_minor;

            if ($refunded > $payment->amount_minor) {
                throw new LogicException("Refund {$locked->reference} exceeds the payment.");
            }

            $from = $payment->status;
            $payment->transitionTo(
                $refunded >= $payment->amount_minor ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
                PaymentEventSource::Refund,
                ['refunded_minor' => $refunded],
                array_filter(['refund' => $locked->reference, 'amount_minor' => $locked->amount_minor, 'gateway_refund_id' => $gatewayRefundId ?? $locked->gateway_refund_id, 'manual_reference' => $manualReference]),
                $webhookEvent,
                $actor,
            );

            $locked->forceFill([
                'status' => RefundStatus::Succeeded,
                'gateway_refund_id' => $gatewayRefundId ?? $locked->gateway_refund_id,
                'manual_reference' => $manualReference,
                'completed_by' => $actor?->getKey(),
                'completed_at' => now(),
                'failure_reason' => null,
            ])->save();

            CreditNote::create([
                'refund_id' => $locked->getKey(),
                'payment_id' => $payment->getKey(),
                'number' => $this->numbers->next(DocumentNumbers::CREDIT_NOTE),
                'customer_id' => $payment->customer_id,
                'customer_snapshot' => Invoice::snapshotOf($payment->customer),
                'seller_snapshot' => $this->seller->snapshot(),
                'amount_minor' => $locked->amount_minor,
                'currency' => $locked->currency,
                'reason' => $locked->reason,
                'issued_at' => now(),
            ]);

            if ($payment->milestone_id !== null) {
                $this->recomputeMilestone->handle($payment->milestone_id);
            }

            $this->audit->record(
                operation: 'refund.succeeded',
                auditable: $locked,
                user: $actor,
                actorType: $actor ? ActorType::User : ActorType::System,
                actorLabel: $actor ? null : 'Gateway refund webhook',
                before: ['payment_status' => $from->value, 'refunded_minor' => $refunded - $locked->amount_minor],
                after: ['payment_status' => $payment->status->value, 'refunded_minor' => $refunded, 'reference' => $locked->reference, 'manual' => $manualReference !== null],
            );

            $completed = true;

            return $locked;
        });

        if ($completed) {
            $this->announce($refund);
        }

        return $refund;
    }

    private function announce(Refund $refund): void
    {
        $refund->load(['payment', 'requester', 'creditNote']);

        Notification::send($this->recipients->clientUsers($refund->customer_id), new RefundStatusChanged($refund, forClient: true));

        $staff = $this->recipients->financeTeam();

        if ($refund->requester !== null && ! $staff->contains($refund->requester)) {
            $staff->push($refund->requester);
        }

        Notification::send($staff, new RefundStatusChanged($refund, forClient: false));
    }
}
