<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Projects (P05) and Project Details (P06 overview, P07 milestones, documents tab). Every lookup is scoped to
 * the current customer, so another customer's project is a 404. Internal notes and internal documents are
 * never loaded here.
 */
class ProjectController extends Controller
{
    /**
     * @var list<string>
     */
    public const TABS = ['overview', 'milestones', 'documents'];

    public function index(Request $request): View
    {
        $filter = in_array($request->query('status'), ['active', 'completed', 'all'], true) ? $request->query('status') : 'active';

        $projects = Project::query()
            ->forCurrentCustomer()
            ->with('milestones:id,project_id,title,status,due_date,sort_order')
            ->when($filter === 'active', fn (Builder $query) => $query->active())
            ->when($filter === 'completed', fn (Builder $query) => $query->where('status', ProjectStatus::Completed))
            ->latest()
            ->latest('id')
            ->get();

        return view('portal.projects.index', [
            'projects' => $projects,
            'filter' => $filter,
            'counts' => [
                'active' => Project::query()->forCurrentCustomer()->active()->count(),
                'completed' => Project::query()->forCurrentCustomer()->where('status', ProjectStatus::Completed)->count(),
                'all' => Project::query()->forCurrentCustomer()->count(),
            ],
        ]);
    }

    public function show(Request $request, string $project): View
    {
        $project = Project::query()->forCurrentCustomer()->where('ulid', $project)->firstOrFail();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';

        $project->load([
            'milestones' => fn ($query) => $query->with('dependencies:id,title'),
            'manager:id,name',
            'members:id,name',
        ]);

        $documents = $project->documents()->clientVisible()->with('uploader:id,name')->orderByDesc('version')->get();

        return view('portal.projects.show', [
            'project' => $project,
            'tab' => $tab,
            'updates' => $project->clientUpdates()->with('author:id,name')->latest()->latest('id')->limit(10)->get(),
            // Latest client-visible version of each title first; older versions grouped under it.
            'documentGroups' => $documents->groupBy('title')->sortByDesc(fn ($versions) => $versions->first()->created_at),
            'recentDocuments' => $documents->unique('title')->sortByDesc('created_at')->take(5)->values(),
            'conversationUlid' => $project->conversations()->orderBy('id')->value('ulid'),
            'documentCount' => $documents->unique('title')->count(),
        ]);
    }
}
