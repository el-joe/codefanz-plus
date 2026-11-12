<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Governance\ApplyApprovedChange;
use App\Actions\Governance\ReviewChangeRequest;
use App\Enums\ChangeRequestStatus;
use App\Exceptions\GovernanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideChangeRequestRequest;
use App\Models\ChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ChangeRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ChangeRequest::class);

        $tab = $request->string('status')->toString();
        $status = ChangeRequestStatus::tryFrom($tab);

        $changeRequests = ChangeRequest::query()
            ->with(['requester', 'reviewer'])
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview]),
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.approvals.index', [
            'changeRequests' => $changeRequests,
            'activeStatus' => $status,
        ]);
    }

    public function show(ChangeRequest $changeRequest): View
    {
        Gate::authorize('view', $changeRequest);

        $changeRequest->load(['requester', 'reviewer']);

        return view('admin.approvals.show', ['changeRequest' => $changeRequest]);
    }

    public function decide(DecideChangeRequestRequest $request, ChangeRequest $changeRequest, ReviewChangeRequest $review): RedirectResponse
    {
        $note = $request->validated('note');

        try {
            match ($request->validated('decision')) {
                'approve' => $review->approve($changeRequest, $request->user(), $note),
                'reject' => $review->reject($changeRequest, $request->user(), $note),
                'request_changes' => $review->requestChanges($changeRequest, $request->user(), (string) $note),
            };
        } catch (GovernanceException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
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
}
