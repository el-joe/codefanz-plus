<?php

namespace App\Actions\Refunds;

use App\Enums\RefundStatus;
use App\Exceptions\ManualRefundRequired;
use App\Exceptions\PaymentGatewayException;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundStatusChanged;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use App\Services\Payments\GatewayRefund;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Step 3: send an approved refund to the gateway's refund API. Succeeded → CompleteRefund (ledger, credit note);
 * pending → stays processing until the gateway's refund webhook is confirmed through its API; refused or
 * unreachable → failed (the reserved amount is released); no API refund for this payment → manual_required.
 */
class ProcessRefund
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private CompleteRefund $completeRefund,
        private AuditLogger $audit,
        private DeliveryRecipients $recipients,
    ) {}

    public function handle(Refund $refund, User $actor): Refund
    {
        $claimed = false;

        // Only the call that moves the refund from approved to processing talks to the gateway (no double refunds).
        $refund = DB::transaction(function () use ($refund, &$claimed): Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($locked->status === RefundStatus::Approved) {
                $locked->forceFill(['status' => RefundStatus::Processing, 'processed_at' => now()])->save();
                $claimed = true;
            }

            return $locked;
        });

        if (! $claimed) {
            return $refund;
        }

        $refund->load('payment');

        try {
            $result = $this->gateways->driver($refund->gateway)->refund($refund->payment, $refund->amount_minor);
        } catch (ManualRefundRequired $exception) {
            return $this->settle($refund, RefundStatus::ManualRequired, $exception->getMessage(), $actor);
        } catch (PaymentGatewayException $exception) {
            Log::warning('Gateway refund failed.', ['refund' => $refund->reference, 'gateway' => $refund->gateway, 'error' => $exception->getMessage()]);

            return $this->settle($refund, RefundStatus::Failed, $exception->getMessage(), $actor);
        }

        if ($result->succeeded()) {
            return $this->completeRefund->handle($refund, $actor, gatewayRefundId: $result->gatewayRefundId);
        }

        if ($result->failed()) {
            return $this->settle($refund, RefundStatus::Failed, $result->failureReason ?? 'refund_declined', $actor, $result);
        }

        $refund->forceFill(['gateway_refund_id' => $result->gatewayRefundId])->save();
        $this->audit->record(operation: 'refund.processing', auditable: $refund, user: $actor, after: ['reference' => $refund->reference, 'gateway_refund_id' => $result->gatewayRefundId]);

        return $refund;
    }

    private function settle(Refund $refund, RefundStatus $status, string $reason, User $actor, ?GatewayRefund $result = null): Refund
    {
        $refund = DB::transaction(function () use ($refund, $status, $reason, $result): Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($locked->status !== RefundStatus::Processing) {
                return $locked;
            }

            $locked->forceFill([
                'status' => $status,
                'failure_reason' => Str::limit($reason, 250, ''),
                'gateway_refund_id' => $result?->gatewayRefundId,
            ])->save();

            return $locked;
        });

        $this->audit->record(
            operation: 'refund.'.$status->value,
            auditable: $refund,
            user: $actor,
            before: ['status' => RefundStatus::Processing->value],
            after: ['status' => $status->value, 'reference' => $refund->reference, 'reason' => $refund->failure_reason],
            result: $status === RefundStatus::Failed ? 'failure' : 'success',
        );

        $refund->load(['payment', 'requester']);
        $recipients = $this->recipients->financeTeam();

        if ($refund->requester !== null && ! $recipients->contains($refund->requester)) {
            $recipients->push($refund->requester);
        }

        Notification::send($recipients, new RefundStatusChanged($refund, forClient: false));

        return $refund;
    }
}
