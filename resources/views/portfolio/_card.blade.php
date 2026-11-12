{{-- Project card. Expects $project loaded with PortfolioController::cardRelations(). --}}
@php
    $cardTranslation = $project->exactTranslation(app()->getLocale());
    $cardCover = $project->coverImage();
    $headingTag = $headingTag ?? 'h3';
@endphp

<li class="group relative flex flex-col overflow-hidden rounded-xl border border-line bg-surface transition-shadow hover:shadow-lg">
    <div class="aspect-[16/10] overflow-hidden bg-[linear-gradient(135deg,var(--color-brand-600),var(--color-accent-500))]">
        @if ($cardCover)
            <x-media.picture :media="$cardCover" sizes="(min-width: 1024px) 400px, (min-width: 640px) 50vw, 100vw" class="size-full object-cover" />
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-3 p-5">
        <p class="flex flex-wrap items-center gap-2">
            <x-ui.badge tone="brand" :dot="false">{{ $project->type->label() }}</x-ui.badge>
            @if ($industryName = $project->industry?->exactTranslation(app()->getLocale())?->name)
                <span class="text-xs text-fg-tertiary">{{ $industryName }}</span>
            @endif
        </p>
        <{{ $headingTag }} class="text-lg font-semibold text-fg">
            <a href="{{ route('portfolio.show', ['slug' => $cardTranslation->slug]) }}" class="after:absolute after:inset-0">{{ $cardTranslation->title }}</a>
        </{{ $headingTag }}>
        @if ($cardTranslation->summary)
            <p class="text-sm text-fg-secondary">{{ $cardTranslation->summary }}</p>
        @endif
        @if ($project->technologies->isNotEmpty())
            <ul class="flex flex-wrap gap-1.5" aria-label="{{ __('Technologies') }}">
                @foreach ($project->technologies->take(4) as $technology)
                    <li class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-fg-secondary" dir="auto">{{ $technology->translation()?->name ?? $technology->key }}</li>
                @endforeach
            </ul>
        @endif
        <span class="mt-auto inline-flex items-center gap-1.5 pt-1 text-sm font-semibold text-fg-brand" aria-hidden="true">
            {{ __('View case study') }}
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </span>
    </div>
</li>
