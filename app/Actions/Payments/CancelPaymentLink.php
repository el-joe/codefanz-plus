<?php

namespace App\Actions\Payments;

use App\Enums\PaymentLinkStatus;
use App\Models\PaymentLink;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff withdraw an active payment link. The link stays in the ledger; checkouts already started can still be
 * verified (money the gateway captured is always recorded).
 */
class CancelPaymentLink
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(PaymentLink $link, User $actor): PaymentLink
    {
        return DB::transaction(function () use ($link, $actor): PaymentLink {
            $link = PaymentLink::query()->lockForUpdate()->findOrFail($link->getKey());

            if ($link->status !== PaymentLinkStatus::Active) {
                throw ValidationException::withMessages(['link' => __('Only active payment links can be cancelled.')]);
            }

            $link->forceFill(['status' => PaymentLinkStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $actor->getKey()])->save();

            $this->audit->record(operation: 'payment_link.cancelled', auditable: $link, user: $actor, before: ['status' => PaymentLinkStatus::Active->value], after: ['status' => $link->status->value]);

            return $link;
        });
    }
}
