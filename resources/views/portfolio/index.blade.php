@php
    $locale = app()->getLocale();
    $tabClasses = fn (bool $active): string => 'inline-block whitespace-nowrap rounded-md px-3 py-1.5 type-label-md '.($active ? 'bg-surface text-fg shadow-xs' : 'text-fg-secondary hover:text-fg');
    $fieldClasses = 'h-12 w-full rounded-md border border-line bg-surface type-body-md text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20';
    $tabUrl = fn (?string $category): string => route('portfolio.index', array_filter([...$filters, 'category' => $category]));
    $featuredTranslation = $featured?->exactTranslation($locale);
@endphp

<x-layouts.public
    :title="__('Work that ships and scales')"
    :description="__('Explore websites, SaaS platforms, mobile apps and AI systems we’ve designed and engineered.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('portfolio.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Portfolio')" :title="__('Work that ships and scales')"
                      :lead="__('Explore websites, SaaS platforms, mobile apps and AI systems we’ve designed and engineered.')"
                      :crumbs="[__('Portfolio') => null]" />

    <x-site.section tone="surface" inner="flex flex-col gap-8" aria-labelledby="projects-heading">
        <h2 id="projects-heading" class="sr-only">{{ __('Projects') }}</h2>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            @if ($categories->isNotEmpty())
                <nav aria-label="{{ __('Project categories') }}" class="-mx-5 overflow-x-auto px-5 lg:mx-0 lg:px-0">
                    <ul class="inline-flex gap-1 rounded-lg bg-muted p-1">
                        <li><a href="{{ $tabUrl(null) }}" class="{{ $tabClasses($filters['category'] === '') }}" @if ($filters['category'] === '') aria-current="page" @endif>{{ __('All') }}</a></li>
                        @foreach ($categories as $category)
                            @php($categoryTranslation = $category->exactTranslation($locale))
                            @php($active = $filters['category'] === $categoryTranslation->slug)
                            <li><a href="{{ $tabUrl($categoryTranslation->slug) }}" class="{{ $tabClasses($active) }}" @if ($active) aria-current="page" @endif>{{ $categoryTranslation->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @else
                <span></span>
            @endif

            <form method="GET" action="{{ route('portfolio.index') }}" role="search" data-auto-submit class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($filters['category'] !== '')
                    <input type="hidden" name="category" value="{{ $filters['category'] }}">
                @endif
                @if ($filters['technology'] !== '')
                    <input type="hidden" name="technology" value="{{ $filters['technology'] }}">
                @endif
                <label for="portfolio-search" class="sr-only">{{ __('Search projects') }}</label>
                <div class="relative sm:w-70">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-icon-secondary"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="portfolio-search" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="{{ __('Search projects…') }}"
                           class="{{ $fieldClasses }} ps-10 pe-3">
                </div>
                @if ($industries->isNotEmpty())
                    <label for="portfolio-industry" class="sr-only">{{ __('Industry') }}</label>
                    <div class="relative sm:w-55">
                        <select id="portfolio-industry" name="industry" class="{{ $fieldClasses }} appearance-none ps-3 pe-10">
                            <option value="">{{ __('All industries') }}</option>
                            @foreach ($industries as $industry)
                                @php($industryTranslation = $industry->exactTranslation($locale))
                                <option value="{{ $industryTranslation->slug }}" @selected($filters['industry'] === $industryTranslation->slug)>{{ $industryTranslation->name }}</option>
                            @endforeach
                        </select>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-icon-secondary"><path d="m6 9 6 6 6-6"/></svg>
                    </div>
                @endif
                <noscript><x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button></noscript>
            </form>
        </div>

        @if ($technology)
            <p class="flex flex-wrap items-center gap-2 type-body-sm text-fg-secondary">
                {{ __('Projects built with :technology', ['technology' => $technology->translation()?->name ?? $technology->key]) }}
                <a href="{{ route('portfolio.index', array_filter([...$filters, 'technology' => null])) }}" class="type-label-md text-fg-brand hover:underline">{{ __('Clear') }}</a>
            </p>
        @endif

        @if ($featured && $featuredTranslation)
            @php($featuredCover = $featured->coverImage())
            <article class="group relative grid grid-cols-1 overflow-hidden rounded-[24px] border border-line bg-surface shadow-sm transition hover:border-line-brand hover:shadow-lg lg:grid-cols-[720fr_558fr]" aria-labelledby="featured-project">
                @if ($featuredCover)
                    <div class="aspect-[12/7] overflow-hidden bg-subtle lg:aspect-auto lg:min-h-105">
                        <x-media.picture :media="$featuredCover" sizes="(min-width: 1024px) 720px, 100vw" :eager="true" class="size-full object-cover" />
                    </div>
                @else
                    <x-site.screen-mockup :window="[576, 336]" class="aspect-[12/7] lg:aspect-auto lg:min-h-105" />
                @endif
                <div class="flex flex-col items-start justify-center gap-4 p-6 sm:p-8 lg:p-12">
                    <p class="flex flex-wrap gap-2">
                        <x-ui.badge tone="brand">{{ __('Featured') }}</x-ui.badge>
                        <x-ui.badge tone="neutral" :dot="false">{{ $featured->type->label() }}</x-ui.badge>
                    </p>
                    <h3 id="featured-project" class="type-h4 text-fg sm:type-h3">
                        <a href="{{ route('portfolio.show', ['slug' => $featuredTranslation->slug]) }}" class="after:absolute after:inset-0 after:rounded-[24px] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $featuredTranslation->title }}</a>
                    </h3>
                    @if ($featuredTranslation->summary)
                        <p class="type-body-md text-fg-secondary">{{ $featuredTranslation->summary }}</p>
                    @endif
                    @if ($featured->technologies->isNotEmpty())
                        <ul class="flex flex-wrap gap-2" aria-label="{{ __('Technologies') }}">
                            @foreach ($featured->technologies->take(5) as $technologyItem)
                                <li class="inline-flex items-center gap-2 rounded-full border border-line-brand bg-surface px-4 py-2 type-label-md text-fg" dir="auto"><span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>{{ $technologyItem->translation()?->name ?? $technologyItem->key }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <span class="inline-flex items-center gap-2 rounded-md bg-brand px-4 py-2.5 type-label-md text-fg-on-brand transition-colors group-hover:bg-brand-hover" aria-hidden="true">
                        {{ __('View case study') }}
                        <x-ui.arrow />
                    </span>
                </div>
            </article>
        @endif

        @if ($projects->isEmpty() && ! $featured)
            <x-ui.empty-state :title="$isFiltered ? __('No projects match your filters') : __('Our portfolio is being updated')"
                              :description="$isFiltered ? __('Try another category or search term.') : __('Please check back soon or contact us to discuss your project.')" />
        @elseif ($projects->isNotEmpty())
            <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    @include('portfolio._card', ['project' => $project])
                @endforeach
            </ul>
        @endif

        @if ($projects->total() > 0)
            {{ $projects->onEachSide(1)->links('pagination.site') }}
        @endif
    </x-site.section>

    <x-site.cta-band :title="__('Need a similar solution?')" :lead="__('Tell us what you want to build — we’ll share relevant work and a preliminary estimate.')" :action="__('Request a similar solution')" />
</x-layouts.public>
