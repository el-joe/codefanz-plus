<?php

namespace App\Actions\Refunds;

use App\Contracts\RetrievesGatewayRefunds;
use App\Enums\ActorType;
use App\Enums\RefundStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Refund;
use App\Models\WebhookEvent;
use App\Services\AuditLogger;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

/**
 * A gateway reported on a refund (signature already checked by HandleGatewayWebhook). A refund still processing is
 * re-checked through the gateway's API and completed or failed; refunds already settled make the delivery a
 * no-op, so duplicate refund webhooks never refund twice or issue a second credit note.
 */
class SyncGatewayRefund
{
    public const UNKNOWN = 'unknown';

    public const SETTLED = 'settled';

    public const UPDATED = 'updated';

    public const PENDING = 'pending';

    public function __construct(private PaymentGatewayManager $gateways, private CompleteRefund $completeRefund, private AuditLogger $audit) {}

    /**
     * @throws PaymentGatewayException when the gateway API cannot be reached (the webhook is retried)
     */
    public function handle(string $gatewayKey, string $gatewayRefundId, ?WebhookEvent $webhookEvent = null): string
    {
        $refund = Refund::query()->where('gateway', $gatewayKey)->where('gateway_refund_id', $gatewayRefundId)->first();

        if ($refund === null) {
            return self::UNKNOWN;
        }

        if ($refund->status !== RefundStatus::Processing) {
            return self::SETTLED;
        }

        $driver = $this->gateways->driver($gatewayKey);

        if (! $driver instanceof RetrievesGatewayRefunds) {
            return self::PENDING;
        }

        $result = $driver->refundStatus($refund);

        if ($result->succeeded()) {
            $this->completeRefund->handle($refund, null, gatewayRefundId: $result->gatewayRefundId, webhookEvent: $webhookEvent);

            return self::UPDATED;
        }

        if ($result->failed()) {
            $failed = DB::transaction(function () use ($refund, $result): bool {
                $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());

                if ($locked->status !== RefundStatus::Processing) {
                    return false;
                }

                $locked->forceFill(['status' => RefundStatus::Failed, 'failure_reason' => $result->failureReason ?? 'refund_failed'])->save();

                return true;
            });

            if ($failed) {
                $this->audit->record(operation: 'refund.failed', auditable: $refund, actorType: ActorType::System, actorLabel: 'Gateway refund webhook', before: ['status' => RefundStatus::Processing->value], after: ['status' => RefundStatus::Failed->value, 'reason' => $result->failureReason], result: 'failure');
            }

            return self::UPDATED;
        }

        return self::PENDING;
    }
}
