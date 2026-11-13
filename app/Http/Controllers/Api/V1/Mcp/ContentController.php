<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Actions\Governance\ProposeChange;
use App\Contracts\Approvable;
use App\Enums\ChangeOrigin;
use App\Enums\McpScope;
use App\Exceptions\GovernanceException;
use App\Http\Requests\Admin\SavePageRequest;
use App\Http\Requests\Api\Mcp\ProposeContentChangeRequest;
use App\Http\Resources\Mcp\ChangeRequestResource;
use App\Http\Resources\Mcp\ContentResource;
use App\Models\Language;
use App\Services\Ai\StudioTargets;
use App\Services\Mcp\McpContentTypes;
use App\Services\Mcp\McpErrors;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * list_content / get_content (published items; drafts too when the client also holds content:draft) and
 * propose_content_change, which only ever creates a pending change request through ProposeChange.
 */
class ContentController extends McpController
{
    public function __construct(private McpContentTypes $types) {}

    public function index(Request $request, string $type): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['published', 'draft', 'all'])],
            'search' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', Rule::in(Language::active()->keys()->all())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('codefanz.mcp.max_per_page')],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);
        $visibility = $validated['status'] ?? 'published';

        if ($visibility !== 'published' && ! $this->can($request, McpScope::ContentDraft)) {
            return $this->forbiddenDrafts();
        }

        $search = $validated['search'] ?? null;
        $titleField = $this->types->titleField($type);

        $items = $this->types->query($type, $visibility)
            ->with('translations')
            ->when($search, fn (Builder $query) => $query->whereHas('translations', fn (Builder $translations) => $translations->where($titleField, 'like', '%'.addcslashes((string) $search, '%_\\').'%')))
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return $this->paginated($items, ContentResource::class, $request);
    }

    public function show(Request $request, string $type, string $key): JsonResponse
    {
        $request->validate(['locale' => ['nullable', 'string', Rule::in(Language::active()->keys()->all())]]);

        $item = $this->types->find($type, $key, $this->can($request, McpScope::ContentDraft), $request->query('locale'));
        abort_if($item === null, 404);

        return new JsonResponse(['data' => (new ContentResource($item))->resolve($request)]);
    }

    public function propose(ProposeContentChangeRequest $request, string $type, int $id, ProposeChange $propose, StudioTargets $studio): JsonResponse
    {
        $item = $this->types->find($type, (string) $id, includeDrafts: true);
        abort_if($item === null, 404);

        $validated = $request->validated();
        $locale = $validated['locale'];

        if (isset($validated['expected_version']) && (int) $validated['expected_version'] !== $item->approvableVersion()) {
            throw GovernanceException::staleTarget((int) $validated['expected_version'], $item->approvableVersion());
        }

        $proposed = $this->proposedChanges($item, $type, $locale, $validated['changes'], $studio);
        $client = $this->client($request);

        $changeRequest = $propose->handle(
            target: $item,
            proposed: $proposed,
            origin: ChangeOrigin::Mcp,
            summary: mb_substr($validated['summary'] ?? __('MCP proposal for “:title” (:locale)', ['title' => $studio->titleOf($item), 'locale' => strtoupper($locale)]), 0, 255),
            requestedBy: $client->owner,
            actorLabel: $client->actorLabel(),
            locale: $locale,
        );

        $this->remember($request, $changeRequest);

        return (new ChangeRequestResource($changeRequest))->response()->setStatusCode(201);
    }

    /**
     * Validate the field names and values against the target, convert Markdown for HTML fields and drop values
     * that equal the live content.
     *
     * @param  array<string, string|null>  $changes
     * @return array<string, string|null>
     */
    private function proposedChanges(Model&Approvable $item, string $type, string $locale, array $changes, StudioTargets $studio): array
    {
        $allowed = $item->approvableAttributes();
        $unknown = array_diff(array_keys($changes), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['changes' => __('These fields cannot be changed: :fields. Allowed: :allowed.', ['fields' => implode(', ', $unknown), 'allowed' => implode(', ', $allowed)])]);
        }

        $snapshot = $item->approvableSnapshot($locale);
        $html = $studio->htmlFields($type);
        $proposed = [];
        $errors = [];

        foreach ($changes as $field => $value) {
            $value = $value === null ? null : trim($value);

            if ($value !== null && mb_strlen($value) > $studio->maxLength($field)) {
                $errors["changes.{$field}"] = __('The :field may not be longer than :max characters.', ['field' => $field, 'max' => $studio->maxLength($field)]);

                continue;
            }

            if ($field === 'slug') {
                $value = mb_strtolower((string) $value);

                if (! preg_match(SavePageRequest::SLUG_PATTERN, $value)) {
                    $errors['changes.slug'] = __('Use lowercase letters, numbers and single hyphens only.');

                    continue;
                }

                if ($item->translations()->getRelated()->newQuery()->where('locale', $locale)->where('slug', $value)->where($item->translations()->getForeignKeyName(), '!=', $item->getKey())->exists()) {
                    $errors['changes.slug'] = __('This slug is already used in that language.');

                    continue;
                }
            }

            if ($value !== null) {
                $value = in_array($field, $html, true)
                    ? trim(Str::markdown($value, ['html_input' => 'strip', 'allow_unsafe_links' => false]))
                    : trim(strip_tags($value));
            }

            if ((string) $value !== trim((string) ($snapshot[$field] ?? ''))) {
                $proposed[$field] = $value;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($proposed !== [] && blank($snapshot['slug'] ?? null) && in_array('slug', $allowed, true) && blank($proposed['slug'] ?? null)) {
            throw ValidationException::withMessages(['changes.slug' => __('This item has no :locale version yet: include a slug for it.', ['locale' => strtoupper($locale)])]);
        }

        if ($proposed === []) {
            throw ValidationException::withMessages(['changes' => __('Nothing differs from the live content, so there is nothing to approve.')]);
        }

        return $proposed;
    }

    private function forbiddenDrafts(): JsonResponse
    {
        return McpErrors::response('insufficient_scope', 'Listing unpublished content needs the content:draft scope.', 403, ['required_scope' => McpScope::ContentDraft->value]);
    }
}
