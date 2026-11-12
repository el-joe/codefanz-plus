{{-- Website 11 · Blog: search, category tabs, featured article, grid and pagination. Also category and tag archives. --}}
@php
    $locale = app()->getLocale();
    $tabClasses = fn (bool $active): string => 'inline-block whitespace-nowrap border-b-2 px-1 pb-2 text-sm font-semibold '.($active ? 'border-brand text-fg-brand' : 'border-transparent text-fg-secondary hover:text-fg');
    $categoryName = $category?->exactTranslation($locale)?->name;
    $tagName = $tag?->exactTranslation($locale)?->name;
    $heading = $categoryName ?? ($tagName ? __('Articles tagged “:tag”', ['tag' => $tagName]) : __('Insights on building digital products'));
    $lead = $category?->exactTranslation($locale)?->description ?: __('Engineering, design, SEO and AI — practical guides from our team.');
    $featuredTranslation = $featured?->exactTranslation($locale);
    $crumbs = $category || $tag ? [__('Blog') => route('blog.index'), ($categoryName ?? $tagName) => null] : [__('Blog') => null];
    $action = $category ? route('blog.category', ['slug' => $category->exactTranslation($locale)->slug]) : ($tag ? route('blog.tag', ['slug' => $tag->exactTranslation($locale)->slug]) : route('blog.index'));
@endphp

<x-layouts.public :title="$categoryName ?? $tagName ?? __('Blog')" :description="$lead" :alternates="$alternates" :robots="$search !== '' ? 'noindex, follow' : null">
    <x-slot:head>
        <link rel="alternate" type="application/rss+xml" title="{{ __('CodeFanz blog') }}" href="{{ route('blog.feed') }}">
    </x-slot:head>

    <x-site.page-hero :eyebrow="__('The CodeFanz blog')" :title="$heading" :lead="$lead" :crumbs="$crumbs">
        <form method="GET" action="{{ $action }}" role="search" class="mt-2 flex max-w-lg gap-2">
            <label for="blog-search" class="sr-only">{{ __('Search articles') }}</label>
            <div class="relative flex-1">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-fg-tertiary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="blog-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __('Search articles…') }}"
                       class="w-full rounded-md border border-line bg-surface py-2.5 ps-9 pe-3 text-sm text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20">
            </div>
            <x-ui.button variant="secondary">{{ __('Search') }}</x-ui.button>
        </form>
    </x-site.page-hero>

    <section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-14 lg:px-20 lg:py-20" aria-labelledby="articles-heading">
        <h2 id="articles-heading" class="sr-only">{{ __('Articles') }}</h2>

        @if ($categories->isNotEmpty())
            <nav aria-label="{{ __('Blog categories') }}" class="overflow-x-auto border-b border-line">
                <ul class="flex gap-6">
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
            <p class="flex flex-wrap items-center gap-2 text-sm text-fg-secondary">
                {{ trans_choice(':count result for “:query”|:count results for “:query”', $posts->total(), ['count' => $posts->total(), 'query' => $search]) }}
                <a href="{{ $action }}" class="font-semibold text-fg-brand hover:underline">{{ __('Clear') }}</a>
            </p>
        @endif

        @if ($featured && $featuredTranslation)
            @php($featuredCover = $featured->coverImage())
            @php($featuredCategory = $featured->category?->exactTranslation($locale))
            <article class="group relative grid items-center gap-8 lg:grid-cols-2" aria-labelledby="featured-article" data-featured-post>
                <div class="aspect-[16/10] overflow-hidden rounded-2xl bg-[linear-gradient(135deg,var(--color-ink-900),var(--color-brand-600))]">
                    @if ($featuredCover)
                        <x-media.picture :media="$featuredCover" sizes="(min-width: 1024px) 640px, 100vw" :eager="true" class="size-full object-cover" />
                    @endif
                </div>
                <div class="flex flex-col gap-4">
                    <p class="flex flex-wrap items-center gap-2 text-sm">
                        <x-ui.badge tone="brand">{{ __('Featured') }}</x-ui.badge>
                        <span class="text-fg-tertiary">{{ collect([$featuredCategory?->name, trans_choice(':count min read|:count min read', $featuredTranslation->reading_minutes, ['count' => $featuredTranslation->reading_minutes])])->filter()->implode(' · ') }}</span>
                    </p>
                    <h3 id="featured-article" class="text-2xl font-bold text-fg lg:text-3xl">
                        <a href="{{ route('blog.show', ['slug' => $featuredTranslation->slug]) }}" class="after:absolute after:inset-0">{{ $featuredTranslation->title }}</a>
                    </h3>
                    @if ($featuredTranslation->excerpt)
                        <p class="text-fg-secondary">{{ $featuredTranslation->excerpt }}</p>
                    @endif
                    <p class="flex items-center gap-3 text-sm">
                        <span class="flex size-9 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-fg-brand" aria-hidden="true">{{ $featured->author?->initials() ?? 'CF' }}</span>
                        <span class="flex flex-col">
                            <span class="font-semibold text-fg">{{ $featured->author?->name ?? 'CodeFanz' }}</span>
                            <time datetime="{{ $featured->published_at->toDateString() }}" class="text-xs text-fg-tertiary">{{ $featured->published_at->locale($locale)->translatedFormat('j M Y') }}</time>
                        </span>
                    </p>
                </div>
            </article>
        @endif

        @if ($posts->isEmpty() && ! $featured)
            <x-ui.empty-state :title="$isFiltered ? __('No articles match your search') : __('No articles yet')"
                              :description="$isFiltered ? __('Try another category or search term.') : __('Our first articles are on the way. Please check back soon.')" />
        @elseif ($posts->isNotEmpty())
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('blog._card', ['post' => $post])
                @endforeach
            </ul>
        @endif

        @if ($posts->total() > 0)
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-fg-tertiary">{{ __('Page :page of :pages', ['page' => $posts->currentPage(), 'pages' => $posts->lastPage()]) }}</p>
                {{ $posts->onEachSide(1)->links() }}
            </div>
        @endif
    </section>
</x-layouts.public>
