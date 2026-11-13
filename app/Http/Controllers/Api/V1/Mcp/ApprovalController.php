<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Actions\Governance\ApplyApprovedChange;
use App\Enums\ActorType;
use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Http\Resources\Mcp\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Models\McpToolCall;
use App\Services\AuditLogger;
use App\Services\Mcp\McpErrors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * list_approvals / get_approval: status of change requests (all of them when the owner may review approvals,
 * otherwise only the ones this client proposed). apply_approved_change: applies a change request that a human
 * already approved, through ApplyApprovedChange (status, expiry, version, approver permission and kill switches
 * are re-verified there). MCP can never approve, reject or request changes.
 */
class ApprovalController extends McpController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ChangeRequestStatus::class)],
            'origin' => ['nullable', Rule::enum(ChangeOrigin::class)],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('codefanz.mcp.max_per_page')],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        $changeRequests = $this->visible($request, (bool) ($validated['mine'] ?? false))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['origin'] ?? null, fn ($query, $origin) => $query->where('origin', $origin))
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return $this->paginated($changeRequests, ChangeRequestResource::class, $request);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $changeRequest = $this->visible($request)->where('reference', $reference)->firstOrFail();

        return new JsonResponse(['data' => (new ChangeRequestResource($changeRequest))->resolve($request)]);
    }

    public function apply(Request $request, string $reference, ApplyApprovedChange $apply, AuditLogger $audit): JsonResponse
    {
        $client = $this->client($request);
        $changeRequest = $this->visible($request)->where('reference', $reference)->firstOrFail();
        $this->remember($request, $changeRequest);

        if ($changeRequest->origin !== ChangeOrigin::Mcp) {
            return McpErrors::response('origin_not_allowed', 'MCP can only apply change requests that were proposed through MCP.', 403);
        }

        if (! $client->owner->can('apply', $changeRequest)) {
            return McpErrors::response('owner_not_permitted', "The client's owner needs the {$changeRequest->required_permission->value} permission to apply this change.", 403);
        }

        $changeRequest = $apply->handle($changeRequest, $client->owner);

        $audit->record(
            operation: 'mcp.change_applied',
            auditable: $changeRequest,
            user: $client->owner,
            actorType: ActorType::McpClient,
            actorLabel: $client->actorLabel(),
            after: ['reference' => $changeRequest->reference, 'status' => $changeRequest->status->value],
            changeRequestId: $changeRequest->id,
        );

        return new JsonResponse(['data' => (new ChangeRequestResource($changeRequest))->resolve($request)]);
    }

    /**
     * @return Builder<ChangeRequest>
     */
    private function visible(Request $request, bool $onlyMine = false): Builder
    {
        $client = $this->client($request);
        $query = ChangeRequest::query();

        if ($onlyMine || ! $client->owner->can('viewAny', ChangeRequest::class)) {
            $query->whereIn('id', McpToolCall::query()->where('mcp_client_id', $client->getKey())->whereNotNull('change_request_id')->select('change_request_id'));
        }

        return $query;
    }
}
