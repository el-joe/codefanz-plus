<?php

namespace App\Actions\Payments;

use App\Actions\Billing\SettleInvoices;
use App\Enums\ActorType;
use App\Enums\PaymentEventSource;
use App\Enums\PaymentLinkStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\PaymentReceived;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use App\Services\DocumentNumbers;
use App\Services\Payments\GatewayVerification;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\SellerDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * The ONLY place that marks a payment paid (docs/DATABASE_DESIGN.md §3 rule 1).
 *
 * Triggered by a signature-checked webhook, the return URL or a staff check, it always asks the gateway's API for
 * the real status first. Then, in one transaction holding a row lock on the payment, it idempotently applies the
 * result: payment_events entry, single-use link marked used, milestone paid recomputed from the ledger, receipt
 * issued, linked invoices settled and an audit entry. Repeated calls (duplicate webhooks, page reloads) are no-ops.
 */
class VerifyPayment
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private AuditLogger $audit,
        private RecomputeMilestonePaid $recomputeMilestone,
        private DocumentNumbers $numbers,
        private SettleInvoices $settleInvoices,
        private DeliveryRecipients $recipients,
        private SellerDetails $seller,
    ) {}

    /**
     * @throws PaymentGatewayException when the gateway cannot be asked (the payment stays as it was)
     */
    public function handle(Payment $payment, PaymentEventSource $source, ?WebhookEvent $webhookEvent = null, ?User $actor = null): Payment
    {
        // Settled payments need no gateway round trip (duplicate webhooks, reloaded return pages).
        if (! $payment->status->isOpen() || $payment->gateway_payment_id === null) {
            return $payment;
        }

        $verification = $this->gateways->driver($payment->gateway)->verifyPayment($payment);
        $becamePaid = false;

        $payment = DB::transaction(function () use ($payment, $verification, $source, $webhookEvent, $actor, &$becamePaid): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (! $locked->status->isOpen() || $verification->status === PaymentStatus::Pending || $verification->status === $locked->status) {
                return $locked;
            }

            if ($verification->status === PaymentStatus::Paid) {
                if ($verification->amountMinor !== $locked->amount_minor || $verification->currency !== $locked->currency) {
                    $this->reportMismatch($locked, $verification, $actor);

                    return $locked;
                }

                $this->markPaid($locked, $verification, $source, $webhookEvent, $actor);
                $becamePaid = true;

                return $locked;
            }

            $locked->transitionTo($verification->status, $source, [
                'verified_at' => now(),
                'verified_via' => $source,
                'failure_reason' => $verification->failureReason,
                'metadata' => [...($locked->metadata ?? []), ...$verification->metadata],
            ], ['gateway_status' => $verification->metadata], $webhookEvent, $actor);

            $this->audit->record(operation: 'payment.'.$verification->status->value, auditable: $locked, user: $actor, actorType: $actor ? ActorType::User : ActorType::System, actorLabel: $actor ? null : $source->label(), before: ['status' => PaymentStatus::Pending->value], after: ['status' => $verification->status->value, 'reason' => $verification->failureReason, 'source' => $source->value]);

            return $locked;
        });

        if ($becamePaid) {
            $this->announce($payment);
        }

        return $payment;
    }

    private function markPaid(Payment $payment, GatewayVerification $verification, PaymentEventSource $source, ?WebhookEvent $webhookEvent, ?User $actor): void
    {
        $from = $payment->status;
        $fee = $verification->feeMinor;

        $payment->transitionTo(PaymentStatus::Paid, $source, [
            'paid_at' => $verification->paidAt ?? now(),
            'verified_at' => now(),
            'verified_via' => $source,
            'fee_minor' => $fee,
            'net_minor' => $fee !== null ? $payment->amount_minor - $fee : null,
            'failure_reason' => null,
            'metadata' => [...($payment->metadata ?? []), ...$verification->metadata],
        ], ['gateway_status' => $verification->metadata, 'amount_minor' => $verification->amountMinor, 'fee_minor' => $fee], $webhookEvent, $actor);

        $link = PaymentLink::query()->lockForUpdate()->findOrFail($payment->payment_link_id);
        $link->forceFill([
            'uses' => $link->uses + 1,
            'status' => $link->single_use && $link->status === PaymentLinkStatus::Active ? PaymentLinkStatus::Used : $link->status,
        ])->save();

        if ($payment->milestone_id !== null) {
            $this->recomputeMilestone->handle($payment->milestone_id);
        }

        $payment->load('customer');

        Receipt::create([
            'payment_id' => $payment->getKey(),
            'number' => $this->numbers->next(DocumentNumbers::RECEIPT),
            'customer_id' => $payment->customer_id,
            'customer_snapshot' => Invoice::snapshotOf($payment->customer),
            'seller_snapshot' => $this->seller->snapshot(),
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'issued_at' => now(),
        ]);

        $this->settleInvoices->handle($payment);

        $this->audit->record(
            operation: 'payment.verified',
            auditable: $payment,
            user: $actor,
            actorType: $actor ? ActorType::User : ActorType::System,
            actorLabel: $actor ? null : $source->label(),
            before: ['status' => $from->value],
            after: ['status' => PaymentStatus::Paid->value, 'reference' => $payment->reference, 'amount_minor' => $payment->amount_minor, 'currency' => $payment->currency, 'fee_minor' => $fee, 'source' => $source->value],
        );
    }

    private function reportMismatch(Payment $payment, GatewayVerification $verification, ?User $actor): void
    {
        Log::error('Gateway amount does not match the payment; not marked paid.', [
            'payment' => $payment->reference,
            'expected' => [$payment->amount_minor, $payment->currency],
            'gateway' => [$verification->amountMinor, $verification->currency],
        ]);

        $this->audit->record(
            operation: 'payment.verification_mismatch',
            auditable: $payment,
            user: $actor,
            actorType: $actor ? ActorType::User : ActorType::System,
            actorLabel: $actor ? null : 'Payment verification',
            before: ['amount_minor' => $payment->amount_minor, 'currency' => $payment->currency],
            after: ['amount_minor' => $verification->amountMinor, 'currency' => $verification->currency],
            result: 'failure',
        );
    }

    private function announce(Payment $payment): void
    {
        $payment->load(['receipt', 'project:id,ulid,name,reference', 'milestone:id,title', 'link:id,ulid,reference,description']);

        Notification::send($this->recipients->clientUsers($payment->customer_id), new PaymentReceived($payment, forClient: true));
        Notification::send($this->recipients->financeTeam(), new PaymentReceived($payment, forClient: false));

        PaymentSucceeded::dispatch($payment);
    }
}
