<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Blog\SavePost;
use App\Enums\ChangeRequestStatus;
use App\Enums\MediaStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePostRequest;
use App\Models\ChangeRequest;
use App\Models\Language;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTranslation;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Blog\EditorialChecklist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Post::class);

        $filters = [
            'status' => PostStatus::tryFrom((string) $request->query('status', ''))?->value ?? '',
            'author' => $request->integer('author') ?: null,
            'category' => $request->integer('category') ?: null,
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
        ];

        return view('admin.posts.index', [
            'posts' => Post::query()
                ->with(['translations', 'author', 'category.translations'])
                ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
                ->when($filters['author'], fn (Builder $query, int $author) => $query->where('author_id', $author))
                ->when($filters['category'], fn (Builder $query, int $category) => $query->where('post_category_id', $category))
                ->when($filters['q'] !== '', fn (Builder $query) => $query->whereHas('translations', fn (Builder $translations) => $translations->where('title', 'like', '%'.addcslashes($filters['q'], '\\%_').'%')))
                ->latest('updated_at')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'authors' => User::query()->whereIn('id', Post::query()->whereNotNull('author_id')->select('author_id'))->orderBy('name')->get(['id', 'name']),
            'categories' => PostCategory::query()->with('translations')->ordered()->get(),
            'counts' => Post::query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Post::class);

        $post = new Post(['author_id' => $request->user()->getKey()]);
        $post->setRelation('translations', new EloquentCollection);
        $post->setRelation('tags', new EloquentCollection);
        $post->setRelation('cover', new EloquentCollection);
        $post->setRelation('checklistItems', new EloquentCollection);
        $post->setRelation('author', $request->user());

        return view('admin.posts.form', [
            ...$this->formOptions(),
            'post' => $post,
            'checklist' => app(EditorialChecklist::class)->evaluate($post),
            'pendingChanges' => collect(),
            'revisionCount' => 0,
        ]);
    }

    public function store(SavePostRequest $request, SavePost $save): RedirectResponse
    {
        $post = $save->handle(null, $request->validated(), $request->user())['post'];

        return redirect()->route('admin.posts.edit', $post)->with('toast', __('Article created as a draft.'));
    }

    public function edit(Post $post, EditorialChecklist $checklist): View
    {
        Gate::authorize('update', $post);

        $post->load(['translations', 'tags', 'cover', 'category', 'checklistItems', 'author', 'reviewer']);

        return view('admin.posts.form', [
            ...$this->formOptions(),
            'post' => $post,
            'checklist' => $checklist->evaluate($post, $checklist->storedManual($post)),
            'revisionCount' => $post->revisions()->count(),
            'pendingChanges' => ChangeRequest::query()
                ->whereMorphedTo('target', $post)
                ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview, ChangeRequestStatus::Approved])
                ->latest('id')
                ->get(),
        ]);
    }

    public function update(SavePostRequest $request, Post $post, SavePost $save): RedirectResponse
    {
        $result = $save->handle($post, $request->validated(), $request->user());

        return redirect()->route('admin.posts.edit', $post)->with('toast', $result['approval_withdrawn']
            ? __('Saved. The content changed after approval, so the article is back in draft and needs a new review.')
            : __('Article saved.'));
    }

    /**
     * Live editorial checklist for the unsaved form (the editor posts the whole form while typing).
     */
    public function checklist(Request $request, EditorialChecklist $checklist): View
    {
        Gate::authorize('create', Post::class);

        $validated = $request->validate([
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.*' => ['nullable', 'string', 'max:'.SavePostRequest::MAX_BODY],
            'is_single_language' => ['nullable', 'boolean'],
            'checklist' => ['nullable', 'array'],
        ]);

        $post = new Post(['is_single_language' => (bool) ($validated['is_single_language'] ?? false)]);
        $translations = new EloquentCollection;

        foreach (Language::active()->keys() as $code) {
            $fields = $validated['translations'][$code] ?? [];

            if (blank($fields['title'] ?? null)) {
                continue;
            }

            $translations->push((new PostTranslation)->fill(['locale' => $code, ...collect($fields)->only(Post::TRANSLATED_FIELDS)->all()]));
        }

        $post->setRelation('translations', $translations);
        $manual = collect(EditorialChecklist::manualItems())->mapWithKeys(fn (string $label, string $key): array => [$key => filter_var($request->input("checklist.{$key}", false), FILTER_VALIDATE_BOOLEAN)])->all();

        return view('admin.posts._checklist', ['checklist' => $checklist->evaluate($post, $manual), 'interactive' => true]);
    }

    public function destroy(Request $request, Post $post, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();
        $audit->record(operation: 'post.deleted', auditable: $post, user: $request->user(), before: ['status' => $post->status->value]);

        return redirect()->route('admin.posts.index')->with('toast', __('Article moved to trash.'));
    }

    /**
     * @return array{categories: EloquentCollection<int, PostCategory>, tags: EloquentCollection<int, Tag>, images: EloquentCollection<int, Media>}
     */
    private function formOptions(): array
    {
        return [
            'categories' => PostCategory::query()->with('translations')->ordered()->get(),
            'tags' => Tag::query()->with('translations')->orderBy('id')->get(),
            'images' => Media::query()
                ->where('status', MediaStatus::Ready)
                ->where('mime_type', 'like', 'image/%')
                ->with('variants')
                ->latest('id')
                ->limit(200)
                ->get(),
        ];
    }
}
