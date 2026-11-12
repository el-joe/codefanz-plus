<?php

namespace App\Actions\Governance;

use App\Contracts\Approvable;
use App\Enums\ChangeAction;
use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Exceptions\GovernanceException;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\ChangeRequestProposed;
use App\Services\AuditLogger;
use App\Services\KillSwitches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Creates a pending change request. AI, MCP and automation must use this instead of writing public content.
 */
class ProposeChange
{
    public function __construct(private KillSwitches $killSwitches, private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $proposed
     * @param  list<array{type: string, locale?: string|null, field?: string|null, excerpt?: string|null}>|null  $guardrailFlags  post-generation checks shown to reviewers
     */
    public function handle(
        Model&Approvable $target,
        array $proposed,
        ChangeOrigin $origin,
        string $summary,
        ?User $requestedBy = null,
        ?string $actorLabel = null,
        ?string $locale = null,
        ChangeAction $action = ChangeAction::Update,
        ?array $guardrailFlags = null,
        ?int $aiGenerationId = null,
    ): ChangeRequest {
        $killSwitch = $origin->killSwitch();

        if ($killSwitch !== null && $this->killSwitches->isActive($killSwitch)) {
            throw GovernanceException::killSwitchActive($killSwitch->value);
        }

        $disallowed = array_diff(array_keys($proposed), $target->approvableAttributes());

        if ($disallowed !== []) {
            throw GovernanceException::disallowedAttributes(array_values($disallowed));
        }

        $changeRequest = DB::transaction(function () use ($target, $proposed, $origin, $summary, $requestedBy, $actorLabel, $locale, $action, $guardrailFlags, $aiGenerationId): ChangeRequest {
            $changeRequest = ChangeRequest::create([
                'reference' => 'CR-PENDING',
                'origin' => $origin,
                'actor_label' => $actorLabel ?? $requestedBy?->name,
                'requested_by' => $requestedBy?->getKey(),
                'ai_generation_id' => $aiGenerationId,
                'action' => $action,
                'target_type' => $target->getMorphClass(),
                'target_id' => $target->getKey(),
                'target_version' => $target->approvableVersion(),
                'locale' => $locale,
                'before' => $target->approvableSnapshot($locale),
                'proposed' => $proposed,
                'guardrail_flags' => $guardrailFlags === [] ? null : $guardrailFlags,
                'summary' => $summary,
                'required_permission' => $target->approvalPermission($proposed),
                'status' => ChangeRequestStatus::Pending,
                'expires_at' => now()->addDays(config('codefanz.change_requests.expires_after_days')),
            ]);

            $changeRequest->forceFill([
                'reference' => sprintf('CR-%s-%04d', now()->format('Y'), $changeRequest->id),
            ])->save();

            $this->audit->record(
                operation: 'change_request.proposed',
                auditable: $target,
                user: $requestedBy,
                actorType: $origin->actorType(),
                actorLabel: $changeRequest->actor_label,
                before: $changeRequest->before,
                after: $proposed,
                changeRequestId: $changeRequest->id,
            );

            return $changeRequest;
        });

        if ($origin !== ChangeOrigin::Human) {
            $this->notifyReviewers($changeRequest, $requestedBy);
        }

        return $changeRequest;
    }

    /**
     * Everyone who can decide (holds the required permission), except the person who asked for the proposal.
     */
    private function notifyReviewers(ChangeRequest $changeRequest, ?User $requestedBy): void
    {
        $reviewers = User::query()->holdingPermission($changeRequest->required_permission)->get()
            ->reject(fn (User $reviewer): bool => $requestedBy !== null && $reviewer->is($requestedBy))
            ->values();

        Notification::send($reviewers, new ChangeRequestProposed($changeRequest));
    }
}
