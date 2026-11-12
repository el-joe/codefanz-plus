<?php

namespace App\Actions\Refunds;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundRequested;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use App\Services\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Step 1 of the four-eyes refund workflow: staff ask for a full or partial refund with a reason. The amount is
 * capped, under a row lock on the payment, at what is still refundable (collected − refunded − open requests).
 * Nothing is sent to the gateway until a different finance user approves.
 */
class RequestRefund
{
    public function __construct(private AuditLogger $audit, private DeliveryRecipients $recipients) {}

    /**
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $requester, int $amountMinor, string $reason): Refund
    {
        $refund = DB::transaction(function () use ($payment, $requester, $amountMinor, $reason): Refund {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (! $locked->status->isCollected()) {
                throw ValidationException::withMessages(['amount' => __('Only collected payments can be refunded.')])->errorBag('refund');
            }

            $refundable = $locked->refundableMinor();

            if ($amountMinor < 1 || $amountMinor > $refundable) {
                throw ValidationException::withMessages(['amount' => __('The refund can be at most :amount.', ['amount' => Money::plain($refundable, $locked->currency)])])->errorBag('refund');
            }

            $refund = Refund::create([
                'payment_id' => $locked->getKey(),
                'customer_id' => $locked->customer_id,
                'gateway' => $locked->gateway,
                'amount_minor' => $amountMinor,
                'currency' => $locked->currency,
                'reason' => $reason,
                'requested_by' => $requester->getKey(),
            ])->refresh();

            $this->audit->record(operation: 'refund.requested', auditable: $refund, user: $requester, after: [
                'reference' => $refund->reference, 'payment' => $locked->reference, 'amount_minor' => $amountMinor, 'currency' => $locked->currency, 'reason' => $reason,
            ]);

            return $refund;
        });

        $refund->load('payment:id,ulid,reference');

        Notification::send(
            $this->recipients->financeTeam()->reject(fn (User $user): bool => $user->is($requester))->values(),
            new RefundRequested($refund, $requester->name),
        );

        return $refund;
    }
}
