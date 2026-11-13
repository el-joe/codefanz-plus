{{-- Figma "Card/Project". Expects $project loaded with PortfolioController::cardRelations(). Without a cover the thumbnail shows the designed gradient + product window. --}}
@php
    $cardTranslation = $project->exactTranslation(app()->getLocale());
    $cardCover = $project->coverImage();
    $headingTag = $headingTag ?? 'h3';
    $cardGradients = [
        'bg-[linear-gradient(135deg,var(--color-brand-600)_12.5%,var(--color-accent-400)_75%)]',
        'bg-[linear-gradient(135deg,var(--color-warning-500)_12.5%,var(--color-danger-500)_75%)]',
        'bg-[linear-gradient(135deg,var(--color-accent-900)_12.5%,var(--color-accent-400)_75%)]',
    ];
@endphp

<li class="group relative flex flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-xs transition hover:border-line-brand hover:shadow-lg">
    <div class="relative aspect-[41/24] overflow-hidden {{ $cardGradients[$project->id % count($cardGradients)] }}">
        @if ($cardCover)
            <x-media.picture :media="$cardCover" sizes="(min-width: 1024px) 410px, (min-width: 768px) 50vw, 100vw" class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]" />
        @else
            <x-site.window-mock class="absolute start-1/2 top-[6%] w-[94%] -translate-x-1/2 rtl:translate-x-1/2 transition-transform duration-300 group-hover:-translate-y-1" />
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
            <a href="{{ route('portfolio.show', ['slug' => $cardTranslation->slug]) }}" class="after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $cardTranslation->title }}</a>
        </{{ $headingTag }}>
        @if ($cardTranslation->summary)
            <p class="type-body-sm text-fg-secondary">{{ $cardTranslation->summary }}</p>
        @endif
        @if ($project->technologies->isNotEmpty())
            <ul class="flex flex-wrap gap-1.5" aria-label="{{ __('Technologies') }}">
                @foreach ($project->technologies->take(4) as $technology)
                    <li class="rounded-sm bg-subtle px-2 py-[3px] type-body-xs text-fg-secondary" dir="auto">{{ $technology->translation()?->name ?? $technology->key }}</li>
                @endforeach
            </ul>
        @endif
        <span class="mt-auto inline-flex items-center gap-1.5 pt-1 type-label-md text-fg-brand" aria-hidden="true">
            {{ __('View case study') }}
            <x-ui.arrow :size="16" class="transition-transform group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" />
        </span>
    </div>
</li>
