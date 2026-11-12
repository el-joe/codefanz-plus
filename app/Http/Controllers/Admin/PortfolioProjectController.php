<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SavePortfolioProject;
use App\Actions\Content\TranslatedList;
use App\Enums\ChangeRequestStatus;
use App\Enums\ContentStatus;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePortfolioProjectRequest;
use App\Models\ChangeRequest;
use App\Models\Industry;
use App\Models\Language;
use App\Models\Media;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Technology;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortfolioProjectController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PortfolioProject::class);

        return view('admin.portfolio.index', [
            'projects' => PortfolioProject::query()
                ->with(['translations', 'category.translations', 'industry.translations'])
                ->ordered()
                ->paginate(30),
            'categories' => PortfolioCategory::query()->with('translations')->withCount('projects')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PortfolioProject::class);

        return view('admin.portfolio.form', [
            ...$this->formOptions(),
            'project' => new PortfolioProject,
            'keyFacts' => [],
            'pendingChanges' => collect(),
        ]);
    }

    public function store(SavePortfolioProjectRequest $request, SavePortfolioProject $save): RedirectResponse
    {
        $project = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.portfolio.edit', $project)->with('toast', __('Project created as a draft.'));
    }

    public function edit(PortfolioProject $project): View
    {
        Gate::authorize('update', $project);

        $project->load(['translations', 'technologies', 'services', 'cover', 'gallery']);

        return view('admin.portfolio.form', [
            ...$this->formOptions(),
            'project' => $project,
            'keyFacts' => TranslatedList::toRows($project->translations, 'key_facts', SavePortfolioProject::KEY_FACT_FIELDS),
            'pendingChanges' => ChangeRequest::query()
                ->whereMorphedTo('target', $project)
                ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview, ChangeRequestStatus::Approved])
                ->latest('id')
                ->get(),
        ]);
    }

    public function update(SavePortfolioProjectRequest $request, PortfolioProject $project, SavePortfolioProject $save): RedirectResponse
    {
        $save->handle($project, $request->validated(), $request->user());

        return redirect()->route('admin.portfolio.edit', $project)->with('toast', __('Project saved.'));
    }

    public function publish(Request $request, PortfolioProject $project, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $project);

        $publish = $project->status !== ContentStatus::Published;

        if ($publish && ! $project->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $project->forceFill([
            'status' => $publish ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $publish ? ($project->published_at ?? now()) : $project->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'portfolio_project.published' : 'portfolio_project.unpublished', auditable: $project, user: $request->user());

        return back()->with('toast', $publish ? __('Project published.') : __('Project moved back to draft.'));
    }

    public function destroy(Request $request, PortfolioProject $project, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();
        $audit->record(operation: 'portfolio_project.deleted', auditable: $project, user: $request->user());

        return redirect()->route('admin.portfolio.index')->with('toast', __('Project moved to trash.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => PortfolioCategory::query()->with('translations')->ordered()->get(),
            'industries' => Industry::query()->with('translations')->ordered()->get(),
            'services' => Service::query()->published()->with('translations')->orderBy('tier')->ordered()->get(),
            'technologies' => Technology::query()->with(['translations', 'category.translations'])->ordered()->get(),
            'images' => Media::query()
                ->where('status', MediaStatus::Ready)
                ->where('mime_type', 'like', 'image/%')
                ->latest('id')
                ->limit(200)
                ->get(['id', 'uuid', 'original_name', 'alt']),
        ];
    }
}
