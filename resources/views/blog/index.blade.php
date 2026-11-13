{{-- Website 11 · Blog: search, category tabs, featured article, grid and pagination. Also category and tag archives. --}}
@php
    $locale = app()->getLocale();
    $tabClasses = fn (bool $active): string => '-mb-px inline-block whitespace-nowrap border-b-2 pb-2.5 type-label-md transition-colors '.($active ? 'border-brand text-fg-brand' : 'border-transparent text-fg-secondary hover:text-fg');
    $categoryName = $category?->exactTranslation($locale)?->name;
    $tagName = $tag?->exactTranslation($locale)?->name;
    $heading = $categoryName ?? ($tagName ? __('Articles tagged “:tag”', ['tag' => $tagName]) : __('Insights on building digital products'));
    // Archives get their own description (unique per page for search results) when the category has none.
    $lead = $category?->exactTranslation($locale)?->description ?: match (true) {
        $categoryName !== null => __('Articles about :category from the CodeFanz team: practical engineering, design, SEO and AI guides.', ['category' => $categoryName]),
        $tagName !== null => __('Articles tagged “:tag” from the CodeFanz team: practical engineering, design, SEO and AI guides.', ['tag' => $tagName]),
        default => __('Engineering, design, SEO and AI — practical guides from our team.'),
    };
    $featuredTranslation = $featured?->exactTranslation($locale);
    $crumbs = $category || $tag ? [__('Blog') => route('blog.index'), ($categoryName ?? $tagName) => null] : [__('Blog') => null];
    $action = $category ? route('blog.category', ['slug' => $category->exactTranslation($locale)->slug]) : ($tag ? route('blog.tag', ['slug' => $tag->exactTranslation($locale)->slug]) : route('blog.index'));
@endphp

<x-layouts.public :title="$categoryName ?? ($tagName ? __('Articles tagged “:tag”', ['tag' => $tagName]) : __('Blog'))" :description="$lead" :alternates="$alternates" :robots="$search !== '' ? 'noindex, follow' : null">
    <x-slot:head>
        <link rel="alternate" type="application/rss+xml" title="{{ __('CodeFanz blog') }}" href="{{ route('blog.feed') }}">
    </x-slot:head>

    <x-site.page-hero :eyebrow="__('The CodeFanz blog')" :title="$heading" :lead="$lead" :crumbs="$crumbs" align="center">
        <form method="GET" action="{{ $action }}" role="search" class="mt-2 w-full max-w-lg">
            <label for="blog-search" class="sr-only">{{ __('Search articles') }}</label>
            <div class="relative">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-icon-secondary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="blog-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __('Search articles…') }}"
                       class="h-12 w-full rounded-md border border-line-strong bg-surface ps-10 pe-24 type-body-md text-fg shadow-xs placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20">
                <button type="submit" class="absolute end-1.5 top-1/2 -translate-y-1/2 rounded-md px-3 py-1.5 type-label-md text-fg-brand hover:bg-brand-subtle">{{ __('Search') }}</button>
            </div>
        </form>
    </x-site.page-hero>

    <x-site.section tone="surface" inner="flex flex-col gap-10" aria-labelledby="articles-heading">
        <h2 id="articles-heading" class="sr-only">{{ __('Articles') }}</h2>

        @if ($categories->isNotEmpty())
            <nav aria-label="{{ __('Blog categories') }}" class="-mx-5 overflow-x-auto px-5 lg:mx-0 lg:px-0">
                <ul class="inline-flex gap-6 border-b border-line">
                    <li><a href="{{ route('blog.index') }}" class="{{ $tabClasses(! $category && ! $tag) }}" @if (! $category && ! $tag) aria-current="page" @endif>{{ __('All') }}</a></li>
                    @foreach ($categories as $tab)
                        @php($tabTranslation = $tab->exactTranslation($locale))
                        @php($active = $category?->is($tab) ?? false)
                        <li><a href="{{ route('blog.category', ['slug' => $tabTranslation->slug]) }}" class="{{ $tabClasses($active) }}" @if ($active) aria-current="page" @endif>{{ $tabTranslation->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        @if ($search !== '')
            <p class="flex flex-wrap items-center gap-2 type-body-sm text-fg-secondary">
                {{ trans_choice(':count result for “:query”|:count results for “:query”', $posts->total(), ['count' => $posts->total(), 'query' => $search]) }}
                <a href="{{ $action }}" class="type-label-md text-fg-brand hover:underline">{{ __('Clear') }}</a>
            </p>
        @endif

        @if ($featured && $featuredTranslation)
            @php($featuredCover = $featured->coverImage())
            @php($featuredCategory = $featured->category?->exactTranslation($locale))
            <article class="group relative grid grid-cols-1 items-center gap-6 lg:grid-cols-2 lg:gap-12" aria-labelledby="featured-article" data-featured-post>
                <div class="relative aspect-[16/10] overflow-hidden rounded-[24px] bg-[linear-gradient(137deg,var(--color-ink-900)_12.5%,var(--color-brand-600)_75%)] transition-shadow group-hover:shadow-lg">
                    @if ($featuredCover)
                        <x-media.picture :media="$featuredCover" sizes="(min-width: 1024px) 640px, 100vw" :eager="true" class="size-full object-cover" />
                    @else
                        <span class="absolute -end-[12%] -top-[30%] aspect-square w-[62%] rounded-full border-2 border-accent/60" aria-hidden="true"></span>
                    @endif
                </div>
                <div class="flex flex-col items-start gap-4">
                    <p class="flex flex-wrap items-center gap-2">
                        <x-ui.badge tone="brand">{{ __('Featured') }}</x-ui.badge>
                        <span class="type-body-sm text-fg-tertiary">{{ collect([$featuredCategory?->name, trans_choice(':count min read|:count min read', $featuredTranslation->reading_minutes, ['count' => $featuredTranslation->reading_minutes])])->filter()->implode(' · ') }}</span>
                    </p>
                    <h3 id="featured-article" class="type-h3 text-fg group-hover:text-fg-brand lg:type-h2">
                        <a href="{{ route('blog.show', ['slug' => $featuredTranslation->slug]) }}" class="after:absolute after:inset-0 after:rounded-[24px] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-4 focus-visible:after:outline-line-focus">{{ $featuredTranslation->title }}</a>
                    </h3>
                    @if ($featuredTranslation->excerpt)
                        <p class="type-body-md text-fg-secondary">{{ $featuredTranslation->excerpt }}</p>
                    @endif
                    <p class="flex items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-subtle type-label-md text-fg-brand" aria-hidden="true">{{ $featured->author?->initials() ?? 'CF' }}</span>
                        <span class="flex flex-col">
                            <span class="type-label-md text-fg">{{ $featured->author?->name ?? 'CodeFanz' }}</span>
                            <time datetime="{{ $featured->published_at->toDateString() }}" class="type-body-xs text-fg-tertiary">{{ $featured->published_at->locale($locale)->translatedFormat('j M Y') }}</time>
                        </span>
                    </p>
                </div>
            </article>
        @endif

        @if ($posts->isEmpty() && ! $featured)
            <x-ui.empty-state :title="$isFiltered ? __('No articles match your search') : __('No articles yet')"
                              :description="$isFiltered ? __('Try another category or search term.') : __('Our first articles are on the way. Please check back soon.')" />
        @elseif ($posts->isNotEmpty())
            <ul class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('blog._card', ['post' => $post])
                @endforeach
            </ul>
        @endif

        @if ($posts->total() > 0)
            {{ $posts->onEachSide(1)->links('pagination.site') }}
        @endif
    </x-site.section>
</x-layouts.public>
