<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Actions\Governance\ProposeChange;
use App\Enums\ChangeOrigin;
use App\Enums\SeoAuditStatus;
use App\Enums\SeoSeverity;
use App\Exceptions\GovernanceException;
use App\Http\Requests\Api\Mcp\ProposeSeoMetaRequest;
use App\Http\Resources\Mcp\ChangeRequestResource;
use App\Http\Resources\Mcp\SeoIssueResource;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Services\Ai\StudioTargets;
use App\Services\Mcp\McpContentTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * get_seo_audit / list_seo_issues read the latest completed crawl (nothing is crawled on demand);
 * propose_seo_meta files a pending change request needing seo.approve.
 */
class SeoController extends McpController
{
    public function audit(): JsonResponse
    {
        $audit = $this->latestAudit();

        if ($audit === null) {
            return new JsonResponse(['data' => null, 'meta' => ['message' => 'No completed SEO audit yet. An administrator can run one in Admin → SEO.']]);
        }

        return new JsonResponse(['data' => [
            'id' => $audit->id,
            'finished_at' => $audit->finished_at?->toIso8601String(),
            'pages_crawled' => $audit->pages_crawled,
            'health_score' => $audit->health_score,
            'issues_by_severity' => $audit->summary['by_severity'] ?? [],
            'issues_by_type' => $audit->summary['by_type'] ?? [],
            'open_issues' => $audit->issues()->whereNull('resolved_at')->count(),
        ]]);
    }

    public function issues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'severity' => ['nullable', Rule::enum(SeoSeverity::class)],
            'type' => ['nullable', 'string', 'max:60'],
            'locale' => ['nullable', 'string', 'max:5'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('codefanz.mcp.max_per_page')],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);
        $audit = $this->latestAudit();

        $issues = SeoIssue::query()
            ->where('seo_audit_id', $audit?->id ?? 0)
            ->whereNull('resolved_at')
            ->when($validated['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['locale'] ?? null, fn ($query, $locale) => $query->where('locale', $locale))
            ->orderBy('id')
            ->cursorPaginate($this->perPage($request));

        return $this->paginated($issues, SeoIssueResource::class, $request);
    }

    public function propose(ProposeSeoMetaRequest $request, McpContentTypes $types, ProposeChange $propose, StudioTargets $studio): JsonResponse
    {
        $validated = $request->validated();
        $item = $types->find($validated['type'], (string) $validated['id'], includeDrafts: true);
        abort_if($item === null, 404);

        if (isset($validated['expected_version']) && (int) $validated['expected_version'] !== $item->approvableVersion()) {
            throw GovernanceException::staleTarget((int) $validated['expected_version'], $item->approvableVersion());
        }

        $snapshot = $item->approvableSnapshot($validated['locale']);

        if (blank($snapshot['slug'] ?? null)) {
            throw ValidationException::withMessages(['locale' => __('This item has no content in that language yet.')]);
        }

        $proposed = collect(['seo_title', 'seo_description'])
            ->filter(fn (string $field): bool => array_key_exists($field, $validated) && $validated[$field] !== null)
            ->mapWithKeys(fn (string $field): array => [$field => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $validated[$field])) ?? '')])
            ->filter(fn (string $value, string $field): bool => $value !== trim((string) ($snapshot[$field] ?? '')))
            ->all();

        if ($proposed === []) {
            throw ValidationException::withMessages(['seo_title' => __('Nothing differs from the live content, so there is nothing to approve.')]);
        }

        $client = $this->client($request);
        $changeRequest = $propose->handle(
            target: $item,
            proposed: $proposed,
            origin: ChangeOrigin::Mcp,
            summary: mb_substr($validated['summary'] ?? __('SEO metadata for “:title” (:locale)', ['title' => $studio->titleOf($item), 'locale' => strtoupper($validated['locale'])]), 0, 255),
            requestedBy: $client->owner,
            actorLabel: $client->actorLabel(),
            locale: $validated['locale'],
        );

        $this->remember($request, $changeRequest);

        return (new ChangeRequestResource($changeRequest))->response()->setStatusCode(201);
    }

    private function latestAudit(): ?SeoAudit
    {
        return SeoAudit::query()->where('status', SeoAuditStatus::Completed)->latest('finished_at')->latest('id')->first();
    }
}
