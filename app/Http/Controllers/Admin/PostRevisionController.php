<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\SavePost;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostRevision;
use App\Models\Tag;
use App\Services\Blog\PostRevisions;
use App\Services\Blog\TextDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Revision history with a side-by-side diff and restore (a restore is saved as a new revision).
 */
class PostRevisionController extends Controller
{
    public function index(Request $request, Post $post, PostRevisions $revisions, TextDiff $diff): View
    {
        Gate::authorize('update', $post);

        $post->load('translations');
        $history = $post->revisions()->with('user')->paginate(20)->withQueryString();
        $locale = Language::active()->has((string) $request->query('locale')) ? (string) $request->query('locale') : Language::defaultCode();

        $selected = $request->filled('revision')
            ? $post->revisions()->whereKey($request->integer('revision'))->first()
            : null;
        $current = ['translations' => $revisions->snapshot($post)['translations']];

        return view('admin.posts.revisions', [
            'post' => $post,
            'history' => $history,
            'selected' => $selected,
            'locale' => $locale,
            'rows' => $selected ? $diff->sideBySide($revisions->lines($selected->snapshot, $locale), $revisions->lines($current, $locale)) : [],
        ]);
    }

    public function restore(Request $request, Post $post, PostRevision $revision, SavePost $save): RedirectResponse
    {
        Gate::authorize('update', $post);

        $validated = $request->validate(['lock_version' => ['required', 'integer']]);
        $snapshot = $revision->snapshot;
        $meta = $snapshot['meta'] ?? [];
        $translations = [];

        foreach (Language::active()->keys() as $code) {
            $translations[$code] = $snapshot['translations'][$code] ?? ['title' => null];
        }

        $coverId = $meta['cover_media_id'] ?? null;

        $result = $save->handle($post, [
            'lock_version' => (int) $validated['lock_version'],
            'post_category_id' => isset($meta['post_category_id']) && PostCategory::query()->whereKey($meta['post_category_id'])->exists() ? $meta['post_category_id'] : null,
            'tag_ids' => Tag::query()->whereKey($meta['tag_ids'] ?? [])->pluck('id')->all(),
            'is_featured' => (bool) ($meta['is_featured'] ?? false),
            'is_single_language' => (bool) ($meta['is_single_language'] ?? false),
            'cover_media_id' => $coverId && Media::query()->whereKey($coverId)->where('status', MediaStatus::Ready)->exists() ? $coverId : null,
            'translations' => $translations,
        ], $request->user(), 'restored', $revision->getKey());

        return redirect()->route('admin.posts.edit', $post)->with('toast', $result['approval_withdrawn']
            ? __('Revision restored as a new version. The article is back in draft and needs a new review.')
            : __('Revision restored as a new version.'));
    }
}
