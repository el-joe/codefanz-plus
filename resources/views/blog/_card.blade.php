{{-- Article card. Expects $post loaded with Post::cardRelations(). --}}
@php
    $cardLocale = app()->getLocale();
    $cardTranslation = $post->exactTranslation($cardLocale);
    $cardCover = $post->coverImage();
    $cardCategory = $post->category?->exactTranslation($cardLocale);
    $headingTag = $headingTag ?? 'h3';
@endphp

<li class="group relative flex flex-col overflow-hidden rounded-xl border border-line bg-surface transition-shadow hover:shadow-lg" data-post-card>
    <div class="aspect-[16/10] overflow-hidden bg-[linear-gradient(135deg,var(--color-brand-600),var(--color-accent-500))]">
        @if ($cardCover)
            <x-media.picture :media="$cardCover" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" class="size-full object-cover" />
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-3 p-5">
        <p class="flex flex-wrap items-center gap-2 text-xs">
            @if ($cardCategory)
                <span class="font-semibold text-fg-brand">{{ $cardCategory->name }}</span>
                <span class="text-fg-tertiary" aria-hidden="true">·</span>
            @endif
            <span class="text-fg-tertiary">{{ trans_choice(':count min read|:count min read', $cardTranslation->reading_minutes, ['count' => $cardTranslation->reading_minutes]) }}</span>
        </p>
        <{{ $headingTag }} class="text-lg font-semibold text-fg">
            <a href="{{ route('blog.show', ['slug' => $cardTranslation->slug]) }}" class="after:absolute after:inset-0">{{ $cardTranslation->title }}</a>
        </{{ $headingTag }}>
        @if ($cardTranslation->excerpt)
            <p class="line-clamp-3 text-sm text-fg-secondary">{{ $cardTranslation->excerpt }}</p>
        @endif
        <p class="mt-auto flex items-center gap-2 pt-1 text-xs">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-[0.65rem] font-semibold text-fg-brand" aria-hidden="true">{{ $post->author?->initials() ?? 'CF' }}</span>
            <span class="flex flex-col">
                <span class="font-semibold text-fg">{{ $post->author?->name ?? 'CodeFanz' }}</span>
                <time datetime="{{ $post->published_at?->toDateString() }}" class="text-fg-tertiary">{{ $post->published_at?->locale($cardLocale)->translatedFormat('j M Y') }}</time>
            </span>
        </p>
    </div>
</li>
