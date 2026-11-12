<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\SaveTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTagRequest;
use App\Models\Tag;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TagController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', Tag::class);

        return view('admin.posts.tag-form', ['tag' => new Tag]);
    }

    public function store(SaveTagRequest $request, SaveTag $save): RedirectResponse
    {
        $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.post-categories.index')->with('toast', __('Tag created.'));
    }

    public function edit(Tag $tag): View
    {
        Gate::authorize('update', $tag);

        return view('admin.posts.tag-form', ['tag' => $tag->load('translations')]);
    }

    public function update(SaveTagRequest $request, Tag $tag, SaveTag $save): RedirectResponse
    {
        $save->handle($tag, $request->validated(), $request->user());

        return redirect()->route('admin.post-categories.index')->with('toast', __('Tag saved.'));
    }

    public function destroy(Request $request, Tag $tag, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $before = ['translations' => $tag->translationSnapshot(['name', 'slug'])];
        $tag->delete();
        $audit->record(operation: 'tag.deleted', auditable: $tag, user: $request->user(), before: $before);

        return redirect()->route('admin.post-categories.index')->with('toast', __('Tag deleted.'));
    }
}
