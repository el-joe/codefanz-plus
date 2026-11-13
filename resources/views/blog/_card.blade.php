{{-- Figma "Card/Article": rounded thumbnail, meta, title link, excerpt and author. Expects $post loaded with Post::cardRelations(). --}}
@php
    $cardLocale = app()->getLocale();
    $cardTranslation = $post->exactTranslation($cardLocale);
    $cardCover = $post->coverImage();
    $cardCategory = $post->category?->exactTranslation($cardLocale);
    $headingTag = $headingTag ?? 'h3';
    // Figma Blog grid: each placeholder thumbnail gets its own gradient (teal, amber, violet, blue, green, ink).
    $cardGradients = [
        'bg-[linear-gradient(137deg,var(--color-accent-900)_12.5%,var(--color-accent-400)_75%)]',
        'bg-[linear-gradient(137deg,#92400e_12.5%,var(--color-warning-500)_75%)]',
        'bg-[linear-gradient(137deg,var(--color-brand-700)_12.5%,var(--color-brand-300)_75%)]',
        'bg-[linear-gradient(137deg,#1e3a8a_12.5%,#3b82f6_75%)]',
        'bg-[linear-gradient(137deg,#14532d_12.5%,#22c55e_75%)]',
        'bg-[linear-gradient(137deg,var(--color-ink-900)_12.5%,var(--color-brand-600)_75%)]',
    ];
@endphp

<li class="group relative flex flex-col items-start gap-4" data-post-card>
    <div class="relative aspect-[41/23] w-full overflow-hidden rounded-xl {{ $cardGradients[$post->id % count($cardGradients)] }} transition-shadow group-hover:shadow-lg">
        @if ($cardCover)
            <x-media.picture :media="$cardCover" sizes="(min-width: 1024px) 410px, (min-width: 640px) 50vw, 100vw" class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]" />
        @else
            <x-site.window-mock class="absolute start-1/2 top-[6%] w-[94%] -translate-x-1/2 rtl:translate-x-1/2 transition-transform duration-300 group-hover:-translate-y-1" />
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
