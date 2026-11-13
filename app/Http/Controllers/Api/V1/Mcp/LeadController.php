<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Actions\Leads\CreateLead;
use App\Enums\ActorType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\Api\Mcp\CreateLeadRequest;
use App\Http\Resources\Mcp\LeadResource;
use App\Models\Lead;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * list_leads / get_lead (personal data minimised unless the client allows it) and create_lead (source "mcp",
 * with the usual LeadCreated event and team notifications; no auto-reply to the person).
 */
class LeadController extends McpController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('codefanz.mcp.max_per_page')],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        $leads = Lead::query()
            ->with('services.translations')
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return $this->paginated($leads, LeadResource::class, $request);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $lead = Lead::query()->with('services.translations')->where('reference', $reference)->firstOrFail();

        return new JsonResponse(['data' => (new LeadResource($lead))->resolve($request)]);
    }

    public function store(CreateLeadRequest $request, CreateLead $createLead, AuditLogger $audit): JsonResponse
    {
        $client = $this->client($request);
        $data = $request->validated();
        $data['country'] = isset($data['country']) ? Str::upper($data['country']) : null;
        $data['locale'] = $data['preferred_locale'];

        $lead = $createLead->handle($data, LeadSource::Mcp, creator: $client->owner, notify: true, sendAutoReply: false);

        $audit->record(
            operation: 'mcp.lead_created',
            auditable: $lead,
            user: $client->owner,
            actorType: ActorType::McpClient,
            actorLabel: $client->actorLabel(),
            after: ['reference' => $lead->reference, 'source' => LeadSource::Mcp->value],
        );

        return (new LeadResource($lead->load('services.translations')))->response()->setStatusCode(201);
    }
}
