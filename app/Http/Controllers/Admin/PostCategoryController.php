<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\SavePostCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePostCategoryRequest;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Blog categories and tags share one taxonomy screen (A03 table pattern).
 */
class PostCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PostCategory::class);

        return view('admin.posts.taxonomy', [
            'categories' => PostCategory::query()->with('translations')->withCount('posts')->ordered()->get(),
            'tags' => Tag::query()->with('translations')->withCount('posts')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PostCategory::class);

        return view('admin.posts.category-form', ['category' => new PostCategory]);
    }

    public function store(SavePostCategoryRequest $request, SavePostCategory $save): RedirectResponse
    {
        $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.post-categories.index')->with('toast', __('Category created.'));
    }

    public function edit(PostCategory $postCategory): View
    {
        Gate::authorize('update', $postCategory);

        return view('admin.posts.category-form', ['category' => $postCategory->load('translations')]);
    }

    public function update(SavePostCategoryRequest $request, PostCategory $postCategory, SavePostCategory $save): RedirectResponse
    {
        $save->handle($postCategory, $request->validated(), $request->user());

        return redirect()->route('admin.post-categories.index')->with('toast', __('Category saved.'));
    }

    public function destroy(Request $request, PostCategory $postCategory, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $postCategory);

        if ($postCategory->posts()->withTrashed()->exists()) {
            return back()->withErrors(['category' => __('Move its articles to another category first.')]);
        }

        $before = ['translations' => $postCategory->translationSnapshot(['name', 'slug'])];
        $postCategory->delete();
        $audit->record(operation: 'post_category.deleted', auditable: $postCategory, user: $request->user(), before: $before);

        return redirect()->route('admin.post-categories.index')->with('toast', __('Category deleted.'));
    }
}
