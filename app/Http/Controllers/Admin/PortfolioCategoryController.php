<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SavePortfolioCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePortfolioCategoryRequest;
use App\Models\PortfolioCategory;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortfolioCategoryController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', PortfolioCategory::class);

        return view('admin.portfolio.category-form', ['category' => new PortfolioCategory]);
    }

    public function store(SavePortfolioCategoryRequest $request, SavePortfolioCategory $save): RedirectResponse
    {
        $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.portfolio.index')->with('toast', __('Category created.'));
    }

    public function edit(PortfolioCategory $portfolioCategory): View
    {
        Gate::authorize('update', $portfolioCategory);

        return view('admin.portfolio.category-form', ['category' => $portfolioCategory->load('translations')]);
    }

    public function update(SavePortfolioCategoryRequest $request, PortfolioCategory $portfolioCategory, SavePortfolioCategory $save): RedirectResponse
    {
        $save->handle($portfolioCategory, $request->validated(), $request->user());

        return redirect()->route('admin.portfolio.index')->with('toast', __('Category saved.'));
    }

    public function destroy(Request $request, PortfolioCategory $portfolioCategory, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $portfolioCategory);

        if ($portfolioCategory->projects()->withTrashed()->exists()) {
            return back()->withErrors(['category' => __('Move its projects to another category first.')]);
        }

        $before = ['translations' => $portfolioCategory->translationSnapshot(['name', 'slug'])];
        $portfolioCategory->delete();
        $audit->record(operation: 'portfolio_category.deleted', auditable: $portfolioCategory, user: $request->user(), before: $before);

        return redirect()->route('admin.portfolio.index')->with('toast', __('Category deleted.'));
    }
}
