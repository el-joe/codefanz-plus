<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveTechnology;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTechnologyRequest;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TechnologyController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Technology::class);

        return view('admin.technologies.index', [
            'categories' => TechnologyCategory::query()
                ->with(['translations', 'technologies' => fn ($query) => $query->withProficiency()->with('translations')->ordered()])
                ->ordered()
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Technology::class);

        return view('admin.technologies.form', ['technology' => new Technology, 'categories' => $this->categories()]);
    }

    public function store(SaveTechnologyRequest $request, SaveTechnology $save): RedirectResponse
    {
        $technology = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.technologies.edit', $technology)->with('toast', __('Technology created.'));
    }

    public function edit(Technology $technology): View
    {
        Gate::authorize('update', $technology);

        $technology = Technology::query()->withProficiency()->with('translations')->findOrFail($technology->getKey());

        return view('admin.technologies.form', ['technology' => $technology, 'categories' => $this->categories()]);
    }

    public function update(SaveTechnologyRequest $request, Technology $technology, SaveTechnology $save): RedirectResponse
    {
        $save->handle($technology, $request->validated(), $request->user());

        return redirect()->route('admin.technologies.edit', $technology)->with('toast', __('Technology saved.'));
    }

    public function destroy(Request $request, Technology $technology, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $technology);

        $technology->delete();
        $audit->record(operation: 'technology.deleted', auditable: $technology, user: $request->user());

        return redirect()->route('admin.technologies.index')->with('toast', __('Technology moved to trash.'));
    }

    /**
     * @return array<int, string>
     */
    private function categories(): array
    {
        return TechnologyCategory::query()->with('translations')->ordered()->get()
            ->mapWithKeys(fn (TechnologyCategory $category): array => [$category->id => $category->translation()?->name ?? $category->key])
            ->all();
    }
}
