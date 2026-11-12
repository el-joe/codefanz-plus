@php
    $locale = app()->getLocale();
    $cover = $project->coverImage();
    $industryName = $project->industry?->exactTranslation($locale)?->name;
    $categoryTranslation = $project->category?->exactTranslation($locale);
    $prose = 'flex flex-col gap-4 leading-relaxed text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-4 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-fg [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_strong]:text-fg [&_ul]:list-disc [&_ul]:ps-6';
    $links = array_filter([
        'live' => $project->live_url ? [__('Live demo'), $project->live_url, 'primary'] : null,
        'android' => $project->android_url ? [__('Google Play'), $project->android_url, 'secondary'] : null,
        'ios' => $project->ios_url ? [__('App Store'), $project->ios_url, 'secondary'] : null,
        'github' => $project->github_url ? [__('Source code'), $project->github_url, 'secondary'] : null,
    ]);
    $facts = array_filter([
        __('Client') => $project->publicClientName() ?? __('Confidential client'),
        __('Industry') => $industryName,
        __('Project type') => $project->type->label(),
        __('Year') => $project->project_date?->year,
    ]);
    $videoEmbed = $project->videoEmbedUrl();
@endphp

<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: $translation->summary"
    :robots="$project->isPubliclyListed() ? null : 'noindex'"
    :alternates="$project->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('portfolio.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-site.page-hero :title="$translation->title" :lead="$translation->summary"
                      :crumbs="[__('Portfolio') => route('portfolio.index'), $translation->title => null]">
        <x-slot:before>
            <p class="flex flex-wrap gap-2">
                <x-ui.badge tone="brand" :dot="false">{{ $project->type->label() }}</x-ui.badge>
                @if ($categoryTranslation)
                    <a href="{{ route('portfolio.index', ['category' => $categoryTranslation->slug]) }}"><x-ui.badge tone="neutral" :dot="false">{{ $categoryTranslation->name }}</x-ui.badge></a>
                @endif
                @if ($industryName)
                    <x-ui.badge tone="accent" :dot="false">{{ $industryName }}</x-ui.badge>
                @endif
            </p>
        </x-slot:before>
        @if ($links !== [])
            <div class="flex flex-wrap gap-3">
                @foreach ($links as $key => [$label, $url, $variant])
                    <x-ui.button :href="$url" :variant="$variant" target="_blank" rel="noopener noreferrer" data-link="{{ $key }}">
                        {{ $label }}
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M7 17 17 7M8 7h9v9"/></svg>
                        <span class="sr-only">({{ __('opens in a new tab') }})</span>
                    </x-ui.button>
                @endforeach
            </div>
        @endif
    </x-site.page-hero>

    @if ($cover || $project->gallery->isNotEmpty())
        <section class="bg-subtle" aria-label="{{ __('Project gallery') }}">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 pb-12 lg:px-20">
                @if ($cover)
                    <x-media.picture :media="$cover" sizes="(min-width: 1280px) 1120px, 100vw" :eager="true" class="w-full rounded-2xl border border-line" />
                @endif
                @if ($project->gallery->isNotEmpty())
                    <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-gallery>
                        @foreach ($project->gallery as $image)
                            <li><x-media.picture :media="$image" sizes="(min-width: 1024px) 270px, 50vw" class="aspect-[16/10] w-full rounded-xl border border-line object-cover" /></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-[1fr_340px] lg:px-20 lg:py-20">
        <div class="flex min-w-0 flex-col gap-10">
            @foreach (['challenge' => [__('The challenge'), $challenge], 'solution' => [__('Our solution'), $solution], 'results' => [__('Results'), $results]] as $section => [$heading, $html])
                @if (trim($html) !== '')
                    <section aria-labelledby="{{ $section }}" class="flex flex-col gap-3">
                        <h2 id="{{ $section }}" class="text-2xl font-bold text-fg">{{ $heading }}</h2>
                        <div class="{{ $prose }}">{!! $html !!}</div>
                    </section>
                @endif
            @endforeach

            @if ($videoEmbed)
                <section aria-labelledby="video" class="flex flex-col gap-3">
                    <h2 id="video" class="text-2xl font-bold text-fg">{{ __('Video walkthrough') }}</h2>
                    <div class="aspect-video overflow-hidden rounded-xl border border-line bg-muted">
                        <iframe src="{{ $videoEmbed }}" title="{{ __('Video: :title', ['title' => $translation->title]) }}" loading="lazy" class="size-full"
                                allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                </section>
            @endif

            @if ($project->services->isNotEmpty())
                <section aria-labelledby="services-delivered" class="flex flex-col gap-4">
                    <h2 id="services-delivered" class="text-2xl font-bold text-fg">{{ __('Services delivered') }}</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($project->services as $service)
                            @php($serviceTranslation = $service->exactTranslation($locale))
                            <li><a href="{{ route('services.show', ['slug' => $serviceTranslation->slug]) }}" class="inline-flex items-center gap-2 rounded-lg border border-line bg-surface px-3 py-2 text-sm font-semibold text-fg hover:border-line-brand"><x-ui.icon :name="$service->icon ?? 'code'" :size="16" />{{ $serviceTranslation->title }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start" aria-labelledby="project-facts">
            <div class="flex flex-col gap-5 rounded-xl border border-line bg-surface p-6">
                <h2 id="project-facts" class="sr-only">{{ __('Project facts') }}</h2>
                <dl class="flex flex-col divide-y divide-line-subtle text-sm">
                    @foreach ($facts as $label => $value)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-fg-tertiary">{{ $label }}</dt>
                            <dd class="text-end font-semibold text-fg">{{ $value }}</dd>
                        </div>
                    @endforeach
                    @foreach ($translation->filledKeyFacts() as $fact)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-fg-tertiary">{{ $fact['label'] }}</dt>
                            <dd class="text-end font-semibold text-fg">{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($project->technologies->isNotEmpty())
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-semibold text-fg">{{ __('Tech stack') }}</p>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($project->technologies as $technology)
                                <x-site.tech-chip :technology="$technology" :href="route('portfolio.index', ['technology' => $technology->key])" />
                            @endforeach
                        </ul>
                        <a href="{{ route('technologies.index') }}" class="text-xs font-semibold text-fg-brand hover:underline">{{ __('See all technologies') }}</a>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    @if ($testimonials->isNotEmpty())
        <section class="mx-auto flex max-w-7xl flex-col gap-6 px-5 pb-14 lg:px-20 lg:pb-20" aria-labelledby="client-feedback">
            <h2 id="client-feedback" class="text-2xl font-bold text-fg">{{ __('What the client says') }}</h2>
            <ul class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <x-site.testimonial-card :testimonial="$testimonial" />
                @endforeach
            </ul>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <div class="bg-subtle">
            <div class="mx-auto max-w-7xl px-5 py-14 lg:px-20 lg:py-20">
                @include('portfolio._related', ['projects' => $related])
            </div>
        </div>
    @endif

    <x-site.cta-band :title="__('Need a similar solution?')" :lead="__('We can adapt this approach to your business. Get a free preliminary estimate.')" :action="__('Request a similar solution')" />
</x-layouts.public>
