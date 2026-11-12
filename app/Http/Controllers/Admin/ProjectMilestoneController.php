<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\ChangeMilestoneStatus;
use App\Actions\Projects\SaveMilestone;
use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMilestoneRequest;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Milestones of a project: create, edit (with dependencies), workflow status, reorder and delete.
 */
class ProjectMilestoneController extends Controller
{
    public function create(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('admin.projects.milestone-form', [
            'project' => $project->load('milestones:id,project_id,title,sort_order'),
            'milestone' => new Milestone(['currency' => $project->currency]),
            'selectedDependencies' => [],
        ]);
    }

    public function store(SaveMilestoneRequest $request, Project $project, SaveMilestone $save): RedirectResponse
    {
        $save->handle($project, null, $request->milestoneData(), $request->dependencyIds(), $request->user());

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'milestones'])->with('toast', __('Milestone added.'));
    }

    public function edit(Project $project, Milestone $milestone): View
    {
        Gate::authorize('update', $project);

        return view('admin.projects.milestone-form', [
            'project' => $project->load('milestones:id,project_id,title,sort_order'),
            'milestone' => $milestone,
            'selectedDependencies' => $milestone->dependencies()->pluck('milestones.id')->all(),
        ]);
    }

    public function update(SaveMilestoneRequest $request, Project $project, Milestone $milestone, SaveMilestone $save): RedirectResponse
    {
        $save->handle($project, $milestone, $request->milestoneData(), $request->dependencyIds(), $request->user(), (int) $request->validated('lock_version'));

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'milestones'])->with('toast', __('Milestone saved.'));
    }

    public function status(Request $request, Project $project, Milestone $milestone, ChangeMilestoneStatus $change): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate(['status' => ['required', Rule::enum(MilestoneStatus::class)]]);
        $change->handle($milestone, MilestoneStatus::from($validated['status']), $request->user());

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'milestones'])->with('toast', __('Milestone updated.'));
    }

    public function move(Request $request, Project $project, Milestone $milestone, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $project);

        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        DB::transaction(function () use ($project, $milestone, $direction): void {
            $ordered = $project->milestones()->lockForUpdate()->get(['id', 'sort_order'])->values();
            $index = $ordered->search(fn (Milestone $item): bool => $item->is($milestone));
            $swap = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($ordered[$swap])) {
                return;
            }

            $ids = $ordered->pluck('id')->all();
            [$ids[$index], $ids[$swap]] = [$ids[$swap], $ids[$index]];

            foreach ($ids as $position => $id) {
                Milestone::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        $audit->record(operation: 'milestone.moved', auditable: $milestone, user: $request->user(), after: ['direction' => $direction]);

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'milestones']);
    }

    public function destroy(Request $request, Project $project, Milestone $milestone, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ($milestone->paid_minor > 0 || $milestone->paymentLinks()->exists() || Invoice::query()->where('milestone_id', $milestone->getKey())->exists()) {
            return back()->with('toast', __('Milestones with payments, payment links or invoices cannot be deleted.'));
        }

        $milestone->delete();
        $audit->record(operation: 'milestone.deleted', auditable: $milestone, user: $request->user(), before: ['title' => $milestone->title, 'status' => $milestone->status->value, 'project' => $project->reference]);

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'milestones'])->with('toast', __('Milestone deleted.'));
    }
}
