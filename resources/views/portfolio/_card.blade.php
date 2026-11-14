{{--
    Figma "Card/Project" (v2): radius 24, hairline, card shadow; hover = lift and the cover scales 1.04 over 500ms.
    Expects $project loaded with PortfolioController::cardRelations(). Without an uploaded cover the thumbnail uses the
    generated fallback cover for the project id (SiteImages::coverFor, stable across requests).
--}}
@php
    $cardTranslation = $project->exactTranslation(app()->getLocale());
    $cardCover = $project->coverImage();
    $headingTag = $headingTag ?? 'h3';
    $cardCoverSizes = '(min-width: 1024px) 410px, (min-width: 768px) 50vw, 100vw';
@endphp

<li class="group relative flex flex-col overflow-hidden rounded-3xl border border-v2-hairline bg-surface shadow-v2-card transition duration-200 ease-out hover:-translate-y-1 hover:border-brand-600/30 hover:shadow-v2-lift">
    <div class="relative aspect-[41/24] overflow-hidden bg-v2-tint">
        @if ($cardCover)
            <x-media.picture :media="$cardCover" :sizes="$cardCoverSizes" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]" />
        @else
            <x-site.image :name="\App\Services\SiteImages::coverFor($project->id)" :sizes="$cardCoverSizes" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]" />
        @endif
    </div>
    <div class="flex flex-1 flex-col items-start gap-3 p-5">
        <p class="flex flex-wrap items-center gap-2">
            <x-ui.badge tone="brand" :dot="false">{{ $project->type->label() }}</x-ui.badge>
            @if ($industryName = $project->industry?->exactTranslation(app()->getLocale())?->name)
                <span class="type-body-xs text-fg-tertiary">{{ $industryName }}</span>
            @endif
        </p>
        <{{ $headingTag }} class="type-h5 text-fg">
            <a href="{{ route('portfolio.show', ['slug' => $cardTranslation->slug]) }}" class="after:absolute after:inset-0 after:rounded-3xl focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $cardTranslation->title }}</a>
        </{{ $headingTag }}>
        @if ($cardTranslation->summary)
            <p class="type-body-sm text-fg-secondary">{{ $cardTranslation->summary }}</p>
        @endif
        @if ($project->technologies->isNotEmpty())
            <ul class="flex flex-wrap gap-1.5" aria-label="{{ __('Technologies') }}">
                @foreach ($project->technologies->take(4) as $technology)
                    <li class="rounded-full bg-v2-tint px-2.5 py-[3px] type-body-xs text-fg-secondary" dir="auto">{{ $technology->translation()?->name ?? $technology->key }}</li>
                @endforeach
            </ul>
        @endif
        <span class="mt-auto inline-flex items-center gap-1.5 pt-1 type-label-md text-fg-brand" aria-hidden="true">
            {{ __('View case study') }}
            <x-ui.arrow :size="16" class="transition-transform duration-200 group-hover:translate-x-1 rtl:group-hover:-translate-x-1" />
        </span>
    </div>
</li>
