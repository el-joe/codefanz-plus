{{-- Website 12 · Blog Article: byline, share, cover, table of contents, rich body, author bio, related articles. --}}
@php
    $locale = app()->getLocale();
    $cover = $post->coverImage();
    $categoryTranslation = $post->category?->exactTranslation($locale);
    $author = $post->author;
    $authorTitle = $author?->authorTitle($locale);
    $authorBio = $author?->authorBio($locale);
    $toc = collect($translation->toc ?? []);
    $url = route('blog.show', ['slug' => $translation->slug]);
    $description = $translation->seo_description ?: $translation->summary();
    $updated = $post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at->copy()->addDay()) ? $post->updated_at : null;
    $share = [
        'x' => [__('Share on X'), 'https://twitter.com/intent/tweet?'.http_build_query(['url' => $url, 'text' => $translation->title])],
        'linkedin' => [__('Share on LinkedIn'), 'https://www.linkedin.com/sharing/share-offsite/?'.http_build_query(['url' => $url])],
        'facebook' => [__('Share on Facebook'), 'https://www.facebook.com/sharer/sharer.php?'.http_build_query(['u' => $url])],
        'whatsapp' => [__('Share on WhatsApp'), 'https://wa.me/?'.http_build_query(['text' => $translation->title.' '.$url])],
    ];
    $mailto = 'mailto:?'.http_build_query(['subject' => $translation->title, 'body' => $url], '', '&', PHP_QUERY_RFC3986);
    $ogImage = $cover ? ($cover->largestVariant('webp') ?? $cover->largestVariant('gif')) : null;
    $articleSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => \Illuminate\Support\Str::limit($translation->title, 110, ''),
        'description' => $description,
        'inLanguage' => $locale,
        'url' => $url,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $translation->canonical_url ?: $url],
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => ($post->updated_at ?? $post->published_at)?->toIso8601String(),
        'wordCount' => $translation->word_count,
        'timeRequired' => 'PT'.$translation->reading_minutes.'M',
        'articleSection' => $categoryTranslation?->name,
        'keywords' => $post->tags->map(fn ($tag) => $tag->exactTranslation($locale)?->name)->filter()->implode(', ') ?: null,
        'image' => $ogImage ? [url($ogImage->url())] : null,
        'author' => array_filter(['@type' => 'Person', 'name' => $author?->name ?? 'CodeFanz', 'jobTitle' => $authorTitle]),
        'publisher' => ['@type' => 'Organization', 'name' => 'CodeFanz', 'url' => route('home')],
    ], fn ($value) => $value !== null && $value !== '');
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect([
            [__('Home'), route('home')],
            [__('Blog'), route('blog.index')],
            ...($categoryTranslation ? [[$categoryTranslation->name, route('blog.category', ['slug' => $categoryTranslation->slug])]] : []),
            [$translation->title, $url],
        ])->values()->map(fn ($crumb, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb[0], 'item' => $crumb[1]])->all(),
    ];
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
@endphp

<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$description"
    :image="$cover"
    og-type="article"
    :canonical="$translation->canonical_url"
    :seoable="$post"
    :alternates="$post->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('blog.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-slot:head>
        @if ($post->published_at)
            <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
        @endif
        @if ($categoryTranslation)
            <meta property="article:section" content="{{ $categoryTranslation->name }}">
        @endif
        <link rel="alternate" type="application/rss+xml" title="{{ __('CodeFanz blog') }}" href="{{ route('blog.feed') }}">
        <script type="application/ld+json">@json($articleSchema, $jsonFlags)</script>
        <script type="application/ld+json">@json($breadcrumbSchema, $jsonFlags)</script>
    </x-slot:head>

    <article aria-labelledby="article-title" data-article class="bg-canvas">
        <header class="container-page flex flex-col gap-5 pt-10 lg:pt-14">
            <nav aria-label="{{ __('Breadcrumb') }}">
                <ol class="flex flex-wrap items-center gap-2 type-body-sm text-fg-secondary">
                    <li><a href="{{ route('home') }}" class="rounded-sm hover:text-fg-brand">{{ __('Home') }}</a></li>
                    <li class="flex items-center gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-icon-secondary rtl:-scale-x-100"><path d="m9 6 6 6-6 6"/></svg>
                        <a href="{{ route('blog.index') }}" class="rounded-sm hover:text-fg-brand">{{ __('Blog') }}</a>
                    </li>
                    @if ($categoryTranslation)
                        <li class="flex items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-icon-secondary rtl:-scale-x-100"><path d="m9 6 6 6-6 6"/></svg>
                            <a href="{{ route('blog.category', ['slug' => $categoryTranslation->slug]) }}" class="rounded-sm text-fg hover:text-fg-brand">{{ $categoryTranslation->name }}</a>
                        </li>
                    @endif
                </ol>
            </nav>

            <p class="flex flex-wrap items-center gap-2.5 type-body-sm text-fg-tertiary">
                @if ($categoryTranslation)
                    <x-ui.badge tone="accent" :dot="false">{{ $categoryTranslation->name }}</x-ui.badge>
                @endif
                <span>{{ trans_choice(':count min read|:count min read', $translation->reading_minutes, ['count' => $translation->reading_minutes]) }}</span>
                @if ($updated)
                    <span aria-hidden="true">·</span>
                    <span>{{ __('Updated :date', ['date' => $updated->locale($locale)->translatedFormat('j M Y')]) }}</span>
                @endif
            </p>

            <h1 id="article-title" class="max-w-[60rem] type-h1 text-balance text-fg lg:type-display-lg">{{ $translation->title }}</h1>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-subtle type-label-md text-fg-brand" aria-hidden="true">{{ $author?->initials() ?? 'CF' }}</span>
                    <span class="flex flex-col">
                        <span class="type-label-md text-fg">{{ $author?->name ?? 'CodeFanz' }}</span>
                        <span class="type-body-xs text-fg-tertiary">
                            @if ($authorTitle){{ $authorTitle }} · @endif
                            <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->locale($locale)->translatedFormat('j M Y') }}</time>
                        </span>
                    </span>
                </p>
                <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('Share this article') }}" data-share>
                    <li>
                        <button type="button" data-copy="{{ $url }}" data-copied-label="{{ __('Copied') }}" class="group/copy flex h-9 items-center gap-2 rounded-full border border-line bg-surface px-2.5 type-label-sm text-icon-primary transition-colors hover:border-line-brand hover:text-fg-brand">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>
                            <span class="max-sm:sr-only" data-copy-text>{{ __('Copy link') }}</span>
                        </button>
                    </li>
                    @foreach ($share as $network => [$label, $href])
                        <li>
                            <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="flex size-9 items-center justify-center rounded-full border border-line bg-surface text-icon-primary transition-colors hover:border-line-brand hover:text-fg-brand" data-share-link="{{ $network }}">
                                <span class="sr-only">{{ $label }} ({{ __('opens in a new tab') }})</span>
                                @switch($network)
                                    @case('x')<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.5z"/></svg>@break
                                    @case('linkedin')<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zm7 0h3.8v1.7h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6V21h-4v-5.6c0-1.3 0-3-1.9-3s-2.1 1.4-2.1 2.9V21h-4z"/></svg>@break
                                    @case('facebook')<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 8h3V4h-3c-2.8 0-4 1.7-4 4.3V10H7v4h3v8h4v-8h3l1-4h-4V8.6c0-.4.3-.6.7-.6z"/></svg>@break
                                    @default<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 1.3-4A8.5 8.5 0 1 1 8.1 19z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8A3.5 3.5 0 0 1 10.7 11.5l.8-1-1-2z"/></svg>
                                @endswitch
                            </a>
                        </li>
                    @endforeach
                    <li>
                        <a href="{{ $mailto }}" class="flex size-9 items-center justify-center rounded-full border border-line bg-surface text-icon-primary transition-colors hover:border-line-brand hover:text-fg-brand">
                            <span class="sr-only">{{ __('Share by email') }}</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="relative mt-5 aspect-[16/9] overflow-hidden rounded-[24px] bg-[linear-gradient(146deg,var(--color-ink-900)_12.5%,var(--color-brand-600)_75%)] sm:aspect-[32/13]">
                @if ($cover)
                    <x-media.picture :media="$cover" sizes="(min-width: 1440px) 1280px, 100vw" :eager="true" class="size-full object-cover" />
                @else
                    <span class="absolute -end-[6%] -top-[40%] aspect-square w-[38%] rounded-full border-2 border-accent/50" aria-hidden="true"></span>
                    <span class="absolute -bottom-[30%] -start-[4%] aspect-square w-[22%] rounded-full bg-brand-400/20 blur-2xl" aria-hidden="true"></span>
                @endif
            </div>
        </header>

        <div class="container-page grid grid-cols-1 gap-10 pt-10 pb-14 lg:grid-cols-[17.5rem_minmax(0,47.5rem)] lg:gap-20 lg:pt-12 lg:pb-24">
            <aside class="lg:sticky lg:top-24 lg:self-start" aria-labelledby="toc-heading">
                @if ($toc->isNotEmpty())
                    <nav class="rounded-xl bg-subtle p-4" aria-labelledby="toc-heading" data-toc>
                        <h2 id="toc-heading" class="mb-3 type-label-md text-fg">{{ __('On this page') }}</h2>
                        <ol class="flex flex-col gap-3 type-body-sm">
                            @foreach ($toc as $entry)
                                <li class="{{ $entry['level'] === 3 ? 'ps-4' : '' }}"><a href="#{{ $entry['id'] }}" class="block rounded-sm border-s-2 border-line ps-2 text-fg-secondary transition-colors hover:text-fg-brand aria-[current=location]:border-brand aria-[current=location]:text-fg-brand">{{ $entry['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </nav>
                @else
                    <span id="toc-heading" class="sr-only">{{ __('On this page') }}</span>
                @endif
            </aside>

            <div class="flex min-w-0 flex-col gap-8">
                @if ($translation->excerpt)
                    <p class="type-body-lg text-fg">{{ $translation->excerpt }}</p>
                @endif

                <div class="prose-article" data-article-body>{!! $translation->body !!}</div>

                @if ($post->tags->isNotEmpty())
                    <ul class="flex flex-wrap gap-2" aria-label="{{ __('Tags') }}">
                        @foreach ($post->tags as $tag)
                            @php($tagTranslation = $tag->exactTranslation($locale))
                            <li><a href="{{ route('blog.tag', ['slug' => $tagTranslation->slug]) }}" class="inline-block rounded-full border border-line bg-surface px-3 py-1 type-label-sm text-fg-secondary transition-colors hover:border-line-brand hover:text-fg-brand">#{{ $tagTranslation->name }}</a></li>
                        @endforeach
                    </ul>
                @endif

                <section class="flex items-start gap-4 rounded-xl bg-subtle p-5" aria-label="{{ __('About the author') }}" data-author-bio>
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-subtle type-label-lg text-fg-brand" aria-hidden="true">{{ $author?->initials() ?? 'CF' }}</span>
                    <div class="flex flex-col gap-1">
                        <p class="type-label-lg text-fg">{{ $author?->name ?? 'CodeFanz' }}</p>
                        @if ($authorTitle)
                            <p class="type-body-sm text-fg-tertiary">{{ $authorTitle }}</p>
                        @endif
                        @if ($authorBio)
                            <p class="type-body-sm text-fg-secondary">{{ $authorBio }}</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <x-site.section tone="surface" inner="flex flex-col gap-8" aria-labelledby="related-heading">
            <h2 id="related-heading" class="type-h3 text-fg">{{ __('Keep reading') }}</h2>
            <ul class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $relatedPost)
                    @include('blog._card', ['post' => $relatedPost])
                @endforeach
            </ul>
        </x-site.section>
    @endif

    <x-site.cta-band :title="__('Have an idea? Let’s build it together.')" :lead="__('Get a free preliminary estimate within 48 hours. No payment required.')" :action="__('Start Your Project')" />
</x-layouts.public>
