<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostCategoryTranslation;
use App\Models\PostTranslation;
use App\Models\Tag;
use App\Models\TagTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public blog (website screens 11–12). Only published articles whose publication date has passed are visible.
 */
class BlogController extends Controller
{
    public const PER_PAGE = 9;

    public const FEED_SIZE = 20;

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function category(Request $request, string $slug): View
    {
        $translation = PostCategoryTranslation::query()->where('locale', app()->getLocale())->where('slug', $slug)->firstOrFail();
        $category = PostCategory::query()->with('translations')->findOrFail($translation->post_category_id);

        return $this->listing($request, category: $category);
    }

    public function tag(Request $request, string $slug): View
    {
        $translation = TagTranslation::query()->where('locale', app()->getLocale())->where('slug', $slug)->firstOrFail();
        $tag = Tag::query()->with('translations')->findOrFail($translation->tag_id);

        return $this->listing($request, tag: $tag);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $translation = PostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('post', fn (Builder $query) => $query->published())
            ->firstOrFail();

        $post = Post::query()
            ->with([
                ...Post::cardRelations(),
                'tags' => fn ($query) => $query->translatedIn($locale)->with('translations'),
            ])
            ->findOrFail($translation->post_id);

        $related = Post::query()
            ->published()
            ->translatedIn($locale)
            ->whereKeyNot($post->getKey())
            ->when($post->post_category_id, fn (Builder $query) => $query->orderByRaw('post_category_id = ? desc', [$post->post_category_id]))
            ->with(Post::cardRelations())
            ->latestPublished()
            ->limit(3)
            ->get();

        return view('blog.show', [
            'post' => $post,
            'translation' => $translation,
            'related' => $related,
        ]);
    }

    /**
     * RSS 2.0 feed of the latest articles in this language.
     */
    public function feed(): Response
    {
        $locale = app()->getLocale();

        $posts = Post::query()
            ->published()
            ->translatedIn($locale)
            ->with(['translations', 'author', 'category.translations'])
            ->latestPublished()
            ->limit(self::FEED_SIZE)
            ->get();

        return response()
            ->view('blog.feed', ['posts' => $posts, 'locale' => $locale], 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    private function listing(Request $request, ?PostCategory $category = null, ?Tag $tag = null): View
    {
        $locale = app()->getLocale();
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $published = fn (): Builder => Post::query()->published()->translatedIn($locale);

        $posts = $published()
            ->when($category, fn (Builder $query) => $query->where('post_category_id', $category->getKey()))
            ->when($tag, fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($tag->getKey())))
            ->when($search !== '', function (Builder $query) use ($locale, $search): void {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $query->whereHas('translations', fn (Builder $translations) => $translations->where('locale', $locale)
                    ->where(fn (Builder $match) => $match->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)));
            });

        $isFiltered = $search !== '' || $category !== null || $tag !== null;
        $featured = ! $isFiltered && $request->integer('page', 1) <= 1
            ? $published()->where('is_featured', true)->with(Post::cardRelations())->latestPublished()->first()
            : null;

        $alternates = match (true) {
            $category !== null => $category->translations->mapWithKeys(fn ($translation): array => [$translation->locale => route('blog.category', ['locale' => $translation->locale, 'slug' => $translation->slug])])->all(),
            $tag !== null => $tag->translations->mapWithKeys(fn ($translation): array => [$translation->locale => route('blog.tag', ['locale' => $translation->locale, 'slug' => $translation->slug])])->all(),
            default => Language::active()->keys()->mapWithKeys(fn (string $code): array => [$code => route('blog.index', ['locale' => $code])])->all(),
        };

        return view('blog.index', [
            'posts' => $posts
                ->when($featured, fn (Builder $query) => $query->whereKeyNot($featured->getKey()))
                ->with(Post::cardRelations())
                ->latestPublished()
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'featured' => $featured,
            'categories' => PostCategory::query()
                ->translatedIn($locale)
                ->whereHas('posts', fn (Builder $query) => $query->published()->translatedIn($locale))
                ->with('translations')
                ->ordered()
                ->get(),
            'category' => $category,
            'tag' => $tag,
            'search' => $search,
            'isFiltered' => $isFiltered,
            'alternates' => $alternates,
        ]);
    }
}
