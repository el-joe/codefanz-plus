<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Milestone;
use App\Models\Payment;

/**
 * milestones.paid_minor is a cache of the ledger: collected payments (Paid / PartiallyRefunded) in the
 * milestone's currency, minus what was refunded. Called by VerifyPayment and the payments:reconcile command.
 */
class RecomputeMilestonePaid
{
    /**
     * Returns [before, after] in minor units.
     *
     * @return array{0: int, 1: int}
     */
    public function handle(Milestone|int $milestone): array
    {
        $milestone = $milestone instanceof Milestone ? $milestone : Milestone::query()->findOrFail($milestone);

        $collected = (int) Payment::query()
            ->where('milestone_id', $milestone->getKey())
            ->where('currency', $milestone->currency)
            ->whereIn('status', PaymentStatus::collected())
            ->selectRaw('COALESCE(SUM(amount_minor - refunded_minor), 0) as collected')
            ->value('collected');

        $before = (int) Milestone::query()->whereKey($milestone->getKey())->value('paid_minor');

        if ($before !== $collected) {
            Milestone::query()->whereKey($milestone->getKey())->update(['paid_minor' => $collected]);
        }

        $milestone->setAttribute('paid_minor', $collected)->syncOriginalAttribute('paid_minor');

        return [$before, $collected];
    }
}
