<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveTechnologyCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTechnologyCategoryRequest;
use App\Models\TechnologyCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TechnologyCategoryController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', TechnologyCategory::class);

        return view('admin.technologies.category-form', ['category' => new TechnologyCategory]);
    }

    public function store(SaveTechnologyCategoryRequest $request, SaveTechnologyCategory $save): RedirectResponse
    {
        $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.technologies.index')->with('toast', __('Category created.'));
    }

    public function edit(TechnologyCategory $technologyCategory): View
    {
        Gate::authorize('update', $technologyCategory);

        return view('admin.technologies.category-form', ['category' => $technologyCategory->load('translations')]);
    }

    public function update(SaveTechnologyCategoryRequest $request, TechnologyCategory $technologyCategory, SaveTechnologyCategory $save): RedirectResponse
    {
        $save->handle($technologyCategory, $request->validated(), $request->user());

        return redirect()->route('admin.technologies.index')->with('toast', __('Category saved.'));
    }

    public function destroy(Request $request, TechnologyCategory $technologyCategory, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $technologyCategory);

        if ($technologyCategory->technologies()->withTrashed()->exists()) {
            return back()->withErrors(['category' => __('Move its technologies to another category first.')]);
        }

        $technologyCategory->delete();
        $audit->record(operation: 'technology_category.deleted', auditable: $technologyCategory, user: $request->user(), before: ['key' => $technologyCategory->key]);

        return redirect()->route('admin.technologies.index')->with('toast', __('Category deleted.'));
    }
}
