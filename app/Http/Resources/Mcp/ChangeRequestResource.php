<?php

namespace App\Http\Resources\Mcp;

use App\Models\ChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Approval status of a change request. The before/proposed values are included on get_approval and right after
 * a proposal; reviewer identity is reduced to a decision and its note.
 *
 * @mixin ChangeRequest
 */
class ChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailed = ! $request->routeIs('api.mcp.list_approvals');

        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'origin' => $this->origin->value,
            'action' => $this->action->value,
            'target' => ['type' => $this->target_type, 'id' => $this->target_id, 'version' => $this->target_version],
            'locale' => $this->locale,
            'summary' => $this->summary,
            'proposed_by' => $this->actor_label,
            'required_permission' => $this->required_permission->value,
            'guardrail_flags' => count($this->guardrail_flags ?? []),
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'applied_at' => $this->applied_at?->toIso8601String(),
            'apply_error' => $this->apply_result['error'] ?? null,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            ...($detailed ? ['before' => $this->before, 'proposed' => $this->proposed] : []),
        ];
    }
}
