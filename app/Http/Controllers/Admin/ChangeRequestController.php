<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Governance\ApplyApprovedChange;
use App\Actions\Governance\ReviewChangeRequest;
use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Enums\Permission;
use App\Exceptions\GovernanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideChangeRequestRequest;
use App\Models\ChangeRequest;
use App\Services\Blog\PostRevisions;
use App\Services\Blog\TextDiff;
use App\Services\KillSwitches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Approval queue (B04): filters, per-field before/after diff, guardrail flags, re-verified checks, decisions,
 * "approve & apply" and bulk approval (never for AI items with guardrail flags). Every rule is enforced again in
 * ReviewChangeRequest / ApplyApprovedChange.
 */
class ChangeRequestController extends Controller
{
    /**
     * Status tabs: key => statuses.
     */
    private const TABS = [
        'pending' => [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview],
        'changes_requested' => [ChangeRequestStatus::ChangesRequested],
        'approved' => [ChangeRequestStatus::Approved],
        'applied' => [ChangeRequestStatus::Applied],
        'closed' => [ChangeRequestStatus::Rejected, ChangeRequestStatus::Failed],
    ];

    public function index(Request $request, KillSwitches $killSwitches): View
    {
        Gate::authorize('viewAny', ChangeRequest::class);

        $filters = $this->filters($request);

        return view('admin.approvals.index', [
            'changeRequests' => $this->query($filters)->with(['requester', 'reviewer'])->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'tabs' => $this->tabCounts($filters),
            'targetTypes' => ChangeRequest::query()->distinct()->orderBy('target_type')->pluck('target_type'),
            'killSwitchesOff' => ! collect(ChangeOrigin::cases())->contains(fn (ChangeOrigin $origin): bool => $origin->killSwitch() !== null && $killSwitches->isActive($origin->killSwitch())),
        ]);
    }

    public function show(Request $request, ChangeRequest $changeRequest, TextDiff $diff, KillSwitches $killSwitches): View
    {
        Gate::authorize('view', $changeRequest);

        $changeRequest->load(['requester', 'reviewer', 'generation', 'target']);
        $target = $changeRequest->target;
        $currentVersion = $target !== null && method_exists($target, 'approvableVersion') ? $target->approvableVersion() : null;
        $user = $request->user();
        $killSwitch = $changeRequest->origin->killSwitch();

        $fields = [];

        foreach ($changeRequest->proposed as $field => $value) {
            $before = (string) ($changeRequest->before[$field] ?? '');
            $after = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
            $fields[$field] = [
                'before' => $before,
                'after' => $after,
                'rows' => $diff->sideBySide($this->lines($before), $this->lines($after)),
            ];
        }

        return view('admin.approvals.show', [
            'changeRequest' => $changeRequest,
            'fields' => $fields,
            'queue' => ChangeRequest::query()->whereIn('status', self::TABS['pending'])->latest('id')->limit(15)->get(),
            'checks' => [
                ['ok' => $currentVersion === $changeRequest->target_version, 'label' => $currentVersion === null
                    ? __('The target no longer exists')
                    : ($currentVersion === $changeRequest->target_version
                        ? __('Target unchanged since the proposal (still v:version)', ['version' => $changeRequest->target_version])
                        : __('Target changed since the proposal (v:then → v:now) — applying will fail', ['then' => $changeRequest->target_version, 'now' => $currentVersion]))],
                ['ok' => $user->can($changeRequest->required_permission->value), 'label' => __('You hold :permission', ['permission' => $changeRequest->required_permission->value])],
                ['ok' => $changeRequest->requested_by !== $user->getKey(), 'label' => __('Not your own proposal')],
                ['ok' => $killSwitch === null || ! $killSwitches->isActive($killSwitch), 'label' => $killSwitch === null ? __('No emergency control applies') : __(':switch is running', ['switch' => $killSwitch->label()])],
                ['ok' => ! $changeRequest->isExpired(), 'label' => $changeRequest->expires_at ? __('Expires :date', ['date' => $changeRequest->expires_at->translatedFormat('j M Y')]) : __('No expiry')],
            ],
        ]);
    }

    public function decide(DecideChangeRequestRequest $request, ChangeRequest $changeRequest, ReviewChangeRequest $review, ApplyApprovedChange $apply): RedirectResponse
    {
        $note = $request->validated('note');
        $decision = $request->validated('decision');

        try {
            match ($decision) {
                'approve', 'approve_apply' => $review->approve($changeRequest, $request->user(), $note),
                'reject' => $review->reject($changeRequest, $request->user(), $note),
                'request_changes' => $review->requestChanges($changeRequest, $request->user(), (string) $note),
            };
        } catch (GovernanceException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        if ($decision === 'approve_apply') {
            try {
                $apply->handle($changeRequest, $request->user());
            } catch (GovernanceException $exception) {
                return redirect()->route('admin.approvals.show', $changeRequest)->withErrors(['apply' => $exception->getMessage()]);
            }

            return redirect()->route('admin.approvals.show', $changeRequest)->with('toast', __('Approved and applied to the live site.'));
        }

        return redirect()->route('admin.approvals.show', $changeRequest)->with('toast', __('Decision recorded.'));
    }

    public function apply(Request $request, ChangeRequest $changeRequest, ApplyApprovedChange $apply): RedirectResponse
    {
        Gate::authorize('apply', $changeRequest);

        try {
            $apply->handle($changeRequest, $request->user());
        } catch (GovernanceException $exception) {
            return back()->withErrors(['apply' => $exception->getMessage()]);
        }

        return redirect()->route('admin.approvals.show', $changeRequest)->with('toast', __('Change applied to the live site.'));
    }

    /**
     * Approve several requests at once. AI-origin requests with guardrail flags, your own proposals and requests
     * you lack the permission for are skipped (each needs an individual review).
     */
    public function bulk(Request $request, ReviewChangeRequest $review): RedirectResponse
    {
        Gate::authorize('viewAny', ChangeRequest::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer'],
        ]);

        $approved = 0;
        $skipped = 0;

        foreach (ChangeRequest::query()->whereKey($validated['ids'])->get() as $changeRequest) {
            if (! self::bulkApprovable($changeRequest) || ! $request->user()->can('review', $changeRequest)) {
                $skipped++;

                continue;
            }

            try {
                $review->approve($changeRequest, $request->user(), __('Bulk approval'));
                $approved++;
            } catch (GovernanceException) {
                $skipped++;
            }
        }

        return back()->with('toast', __(':approved approved, :skipped skipped (flagged AI items need an individual review).', ['approved' => $approved, 'skipped' => $skipped]));
    }

    public static function bulkApprovable(ChangeRequest $changeRequest): bool
    {
        return $changeRequest->status->isReviewable()
            && ! ($changeRequest->origin === ChangeOrigin::Ai && $changeRequest->hasGuardrailFlags());
    }

    /**
     * @return array{tab: string, origin: ?ChangeOrigin, type: ?string, permission: ?Permission}
     */
    private function filters(Request $request): array
    {
        $tab = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        return [
            'tab' => array_key_exists($tab, self::TABS) ? $tab : 'pending',
            'origin' => ChangeOrigin::tryFrom($request->string('origin')->toString()),
            'type' => preg_match('/^[a-z_]{1,40}$/', $type) === 1 ? $type : null,
            'permission' => in_array($permission = Permission::tryFrom($request->string('permission')->toString()), Permission::approvalPermissions(), true) ? $permission : null,
        ];
    }

    /**
     * @param  array{tab: string, origin: ?ChangeOrigin, type: ?string, permission: ?Permission}  $filters
     * @return Builder<ChangeRequest>
     */
    private function query(array $filters, bool $withTab = true): Builder
    {
        return ChangeRequest::query()
            ->when($withTab, fn (Builder $query) => $query->whereIn('status', self::TABS[$filters['tab']]))
            ->when($filters['origin'], fn (Builder $query, ChangeOrigin $origin) => $query->where('origin', $origin))
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('target_type', $type))
            ->when($filters['permission'], fn (Builder $query, Permission $permission) => $query->where('required_permission', $permission));
    }

    /**
     * @param  array{tab: string, origin: ?ChangeOrigin, type: ?string, permission: ?Permission}  $filters
     * @return array<string, int>
     */
    private function tabCounts(array $filters): array
    {
        $counts = $this->query($filters, false)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(self::TABS)->map(fn (array $statuses): int => (int) collect($statuses)->sum(fn (ChangeRequestStatus $status): int => (int) ($counts[$status->value] ?? 0)))->all();
    }

    /**
     * @return list<string>
     */
    private function lines(string $text): array
    {
        return str_contains($text, '</')
            ? PostRevisions::htmlToLines($text)
            : array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn (string $line): bool => $line !== ''));
    }
}
