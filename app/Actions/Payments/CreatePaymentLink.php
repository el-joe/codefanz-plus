<?php

namespace App\Actions\Payments;

use App\Enums\PaymentLinkStatus;
use App\Models\Milestone;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff create a payment request for a project, optionally tied to a milestone. A milestone link is always
 * single use, in the milestone's currency, and capped at the milestone's remaining balance: amount − paid −
 * open links. The milestone row is locked while the cap is checked so two links cannot both take the balance.
 */
class CreatePaymentLink
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{milestone_id: int|null, amount_minor: int, currency: string, description: string|null, expires_at: CarbonInterface|null, single_use: bool, gateways: list<string>}  $data
     *
     * @throws ValidationException
     */
    public function handle(Project $project, array $data, User $actor): PaymentLink
    {
        return DB::transaction(function () use ($project, $data, $actor): PaymentLink {
            $milestone = null;

            if ($data['milestone_id'] !== null) {
                $milestone = Milestone::query()->whereBelongsTo($project)->lockForUpdate()->findOrFail($data['milestone_id']);

                if ($milestone->currency !== $data['currency']) {
                    throw ValidationException::withMessages(['currency' => __('A milestone payment link must use the milestone currency (:currency).', ['currency' => $milestone->currency])])->errorBag('paymentLink');
                }

                $remaining = self::remainingBalance($milestone);

                if ($data['amount_minor'] > $remaining) {
                    throw ValidationException::withMessages(['amount' => __('The amount cannot exceed the milestone’s remaining balance of :amount.', ['amount' => Money::localized($remaining, $milestone->currency)])])->errorBag('paymentLink');
                }
            }

            $link = PaymentLink::create([
                'customer_id' => $project->customer_id,
                'project_id' => $project->getKey(),
                'milestone_id' => $milestone?->getKey(),
                'amount_minor' => $data['amount_minor'],
                'currency' => $data['currency'],
                'description' => $data['description'],
                'expires_at' => $data['expires_at'],
                'single_use' => $milestone !== null ? true : $data['single_use'],
                'gateways' => array_values(array_unique($data['gateways'])),
                'status' => PaymentLinkStatus::Active,
                'created_by' => $actor->getKey(),
            ])->refresh();

            $this->audit->record(operation: 'payment_link.created', auditable: $link, user: $actor, after: [
                'reference' => $link->reference,
                'project' => $project->reference,
                'milestone' => $milestone?->title,
                'amount_minor' => $link->amount_minor,
                'currency' => $link->currency,
                'single_use' => $link->single_use,
                'gateways' => $link->gateways,
                'expires_at' => $link->expires_at?->toIso8601String(),
            ]);

            return $link;
        });
    }

    /**
     * What can still be requested for a milestone: amount − collected − open (unexpired, active) links.
     */
    public static function remainingBalance(Milestone $milestone, ?PaymentLink $except = null): int
    {
        $pending = (int) PaymentLink::query()
            ->where('milestone_id', $milestone->getKey())
            ->open()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->sum('amount_minor');

        return max(0, $milestone->amount_minor - $milestone->paid_minor - $pending);
    }
}
