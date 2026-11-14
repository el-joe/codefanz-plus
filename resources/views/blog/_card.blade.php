{{--
    Figma "Card/Article" (v2): radius-20 cover with the card shadow, meta, title link, excerpt and author. Expects $post loaded
    with Post::cardRelations(). Without an uploaded cover it uses the generated article fallback for the post id.
--}}
@php
    $cardLocale = app()->getLocale();
    $cardTranslation = $post->exactTranslation($cardLocale);
    $cardCover = $post->coverImage();
    $cardCategory = $post->category?->exactTranslation($cardLocale);
    $headingTag = $headingTag ?? 'h3';
    $cardCoverSizes = '(min-width: 1024px) 410px, (min-width: 640px) 50vw, 100vw';
@endphp

<li class="group relative flex flex-col items-start gap-4" data-post-card>
    <div class="relative aspect-[41/23] w-full overflow-hidden rounded-xl border border-v2-hairline bg-v2-tint shadow-v2-card transition-shadow duration-200 group-hover:shadow-v2-lift">
        @if ($cardCover)
            <x-media.picture :media="$cardCover" :sizes="$cardCoverSizes" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]" />
        @else
            <x-site.image :name="\App\Services\SiteImages::coverFor($post->id, \App\Services\SiteImages::ARTICLE_COVERS)" :sizes="$cardCoverSizes" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]" />
        @endif
    </div>
    <p class="flex flex-wrap items-center gap-2">
        @if ($cardCategory)
            <span class="type-label-sm text-fg-brand">{{ $cardCategory->name }}</span>
            <span class="type-body-xs text-fg-tertiary" aria-hidden="true">·</span>
        @endif
        <span class="type-body-xs text-fg-tertiary">{{ trans_choice(':count min read|:count min read', $cardTranslation->reading_minutes, ['count' => $cardTranslation->reading_minutes]) }}</span>
    </p>
    <{{ $headingTag }} class="type-h5 text-fg">
        <a href="{{ route('blog.show', ['slug' => $cardTranslation->slug]) }}" class="after:absolute after:inset-0 after:rounded-xl group-hover:text-fg-brand focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-4 focus-visible:after:outline-line-focus">{{ $cardTranslation->title }}</a>
    </{{ $headingTag }}>
    @if ($cardTranslation->excerpt)
        <p class="line-clamp-3 type-body-sm text-fg-secondary">{{ $cardTranslation->excerpt }}</p>
    @endif
    <p class="mt-auto flex items-center gap-2.5">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-subtle type-label-sm text-fg-brand" aria-hidden="true">{{ $post->author?->initials() ?? 'CF' }}</span>
        <span class="flex flex-col">
            <span class="type-label-sm text-fg">{{ $post->author?->name ?? 'CodeFanz' }}</span>
            <time datetime="{{ $post->published_at?->toDateString() }}" class="type-body-xs text-fg-tertiary">{{ $post->published_at?->locale($cardLocale)->translatedFormat('j M Y') }}</time>
        </span>
    </p>
</li>
