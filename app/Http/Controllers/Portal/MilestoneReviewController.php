<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Projects\ReviewMilestone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ReviewMilestoneRequest;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

/**
 * The client approves a milestone or requests changes (P07). Project and milestone are resolved inside the
 * current customer, so foreign milestones are a 404.
 */
class MilestoneReviewController extends Controller
{
    public function __invoke(ReviewMilestoneRequest $request, string $project, string $milestone, ReviewMilestone $review): RedirectResponse
    {
        $project = Project::query()->forCurrentCustomer()->where('ulid', $project)->firstOrFail();
        $milestone = Milestone::query()->where('project_id', $project->getKey())->where('ulid', $milestone)->firstOrFail();

        $review->handle($milestone, $request->approves(), $request->validated('feedback'), $request->user());

        return redirect()->route('portal.projects.show', ['project' => $project, 'tab' => 'milestones'])
            ->with('toast', $request->approves() ? __('Milestone approved. Thank you!') : __('Your change request was sent to the team.'));
    }
}
