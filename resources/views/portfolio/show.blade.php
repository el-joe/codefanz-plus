@php
    $locale = app()->getLocale();
    $cover = $project->coverImage();
    $industryName = $project->industry?->exactTranslation($locale)?->name;
    $categoryTranslation = $project->category?->exactTranslation($locale);
    $prose = 'flex flex-col gap-3 type-body-md text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-2 [&_h2]:type-h5 [&_h2]:text-fg [&_h3]:type-label-lg [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_strong]:text-fg [&_ul]:list-disc [&_ul]:ps-6';
    $icons = [
        'live' => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
        'android' => '<path d="m6 4 13 8-13 8z"/>',
        'ios' => '<rect x="6" y="3" width="12" height="18" rx="2.5"/><path d="M11 17.5h2"/>',
        'github' => '<path d="m8 8-4 4 4 4"/><path d="m16 8 4 4-4 4"/>',
    ];
    $links = array_filter([
        'live' => $project->live_url ? [__('Live demo'), $project->live_url, 'primary'] : null,
        'android' => $project->android_url ? [__('Google Play'), $project->android_url, 'secondary'] : null,
        'ios' => $project->ios_url ? [__('App Store'), $project->ios_url, 'secondary'] : null,
        'github' => $project->github_url ? [__('Source code'), $project->github_url, 'secondary'] : null,
    ]);
    $mainImage = $cover ?? $project->gallery->first();
    $thumbnails = $cover ? $project->gallery : $project->gallery->slice(1);
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
    :description="$translation->seo_description ?: ($translation->summary ?: $translation->title)"
    :robots="$project->isPubliclyListed() ? null : 'noindex'"
    :image="$cover"
    :seoable="$project"
    :alternates="$project->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('portfolio.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    {{-- No image accent: the project's own cover/gallery (or its generated fallback cover) follows directly. --}}
    <x-site.page-hero :title="$translation->title" :lead="$translation->summary" :image="false"
                      :crumbs="[__('Portfolio') => route('portfolio.index'), $translation->title => null]">
        <x-slot:before>
            <p class="flex flex-wrap gap-2">
                <x-ui.badge tone="brand" :dot="false">{{ $project->type->label() }}</x-ui.badge>
                @if ($categoryTranslation)
                    <a href="{{ route('portfolio.index', ['category' => $categoryTranslation->slug]) }}" class="rounded-full hover:opacity-80"><x-ui.badge tone="neutral" :dot="false">{{ $categoryTranslation->name }}</x-ui.badge></a>
                @endif
                @if ($industryName)
                    <x-ui.badge tone="neutral" :dot="false">{{ $industryName }}</x-ui.badge>
                @endif
            </p>
        </x-slot:before>
        @if ($links !== [])
            <div class="flex flex-wrap gap-3">
                @foreach ($links as $key => [$label, $url, $variant])
                    <x-ui.button :href="$url" :variant="$variant" target="_blank" rel="noopener noreferrer" data-link="{{ $key }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 {{ $key === 'live' ? 'rtl:-scale-x-100' : '' }}">{!! $icons[$key] !!}</svg>
                        {{ $label }}
                        <span class="sr-only">({{ __('opens in a new tab') }})</span>
                    </x-ui.button>
                @endforeach
            </div>
        @endif
    </x-site.page-hero>

    <section class="bg-v2-canvas pt-10 pb-14 lg:pt-16 lg:pb-24" aria-label="{{ __('Project gallery') }}">
        <div class="container-page flex flex-col gap-4">
            @if ($mainImage)
                <x-media.picture :media="$mainImage" sizes="(min-width: 1440px) 1280px, 100vw" :eager="true" class="aspect-[32/15] w-full rounded-4xl border border-v2-hairline object-cover shadow-v2-card max-sm:rounded-3xl" />
            @else
                <div class="aspect-[16/10] overflow-hidden rounded-3xl border border-v2-hairline bg-v2-tint shadow-v2-card sm:aspect-[32/15] lg:rounded-4xl">
                    <x-site.image :name="\App\Services\SiteImages::coverFor($project->id)" sizes="(min-width: 1440px) 1280px, 100vw" class="size-full object-cover" />
                </div>
            @endif
            @if ($thumbnails->isNotEmpty())
                <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-gallery>
                    @foreach ($thumbnails as $image)
                        <li><x-media.picture :media="$image" sizes="(min-width: 1024px) 308px, 50vw" class="aspect-[77/45] w-full rounded-2xl border border-v2-hairline object-cover shadow-v2-card" /></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <x-site.section tone="surface" inner="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-16">
        <div class="flex min-w-0 flex-col gap-10">
            @foreach (['challenge' => [__('The challenge'), $challenge], 'solution' => [__('Our solution'), $solution], 'results' => [__('Results'), $results]] as $section => [$heading, $html])
                @if (trim($html) !== '')
                    <section aria-labelledby="{{ $section }}" class="flex flex-col gap-3">
                        <h2 id="{{ $section }}" class="type-h4 text-fg">{{ $heading }}</h2>
                        <div class="{{ $prose }}">{!! $html !!}</div>
                    </section>
                @endif
            @endforeach

            @if ($videoEmbed)
                <section aria-labelledby="video" class="flex flex-col gap-3">
                    <h2 id="video" class="type-h4 text-fg">{{ __('Video walkthrough') }}</h2>
                    <div class="aspect-video overflow-hidden rounded-xl border border-line bg-muted">
                        <iframe src="{{ $videoEmbed }}" title="{{ __('Video: :title', ['title' => $translation->title]) }}" loading="lazy" class="size-full"
                                allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                </section>
            @endif
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start" aria-labelledby="project-facts">
            <div class="flex flex-col rounded-xl bg-subtle px-6 py-2">
                <h2 id="project-facts" class="sr-only">{{ __('Project facts') }}</h2>
                <dl class="flex flex-col">
                    @foreach ($facts as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-line py-4">
                            <dt class="type-body-sm text-fg-secondary">{{ $label }}</dt>
                            <dd class="text-end type-label-md text-fg">{{ $value }}</dd>
                        </div>
                    @endforeach
                    @foreach ($translation->filledKeyFacts() as $fact)
                        <div class="flex justify-between gap-4 border-b border-line py-4">
                            <dt class="type-body-sm text-fg-secondary">{{ $fact['label'] }}</dt>
                            <dd class="text-end type-label-md text-fg">{{ $fact['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($project->technologies->isNotEmpty())
                    <div class="flex flex-col items-start gap-3 pt-5 pb-4">
                        <p class="type-label-md text-fg">{{ __('Tech stack') }}</p>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($project->technologies as $technology)
                                <x-site.tech-chip :technology="$technology" :href="route('portfolio.index', ['technology' => $technology->key])" />
                            @endforeach
                        </ul>
                        <a href="{{ route('technologies.index') }}" class="type-label-sm text-fg-brand hover:underline">{{ __('See all technologies') }}</a>
                    </div>
                @endif
            </div>
        </aside>
    </x-site.section>

    @if ($project->services->isNotEmpty() || $testimonials->isNotEmpty())
        <x-site.section tone="canvas" inner="flex flex-col gap-8 lg:gap-12" aria-labelledby="what-we-built">
            @if ($project->services->isNotEmpty())
                <x-site.section-heading id="what-we-built" align="start" :eyebrow="__('What we built')" :heading="__('Services delivered')" />
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($project->services as $service)
                        @php($serviceTranslation = $service->exactTranslation($locale))
                        <li class="relative flex items-center gap-3 rounded-lg border border-line bg-surface px-5 py-4 transition hover:border-line-brand hover:shadow-md">
                            <x-ui.check-circle :size="22" />
                            <a href="{{ route('services.show', ['slug' => $serviceTranslation->slug]) }}" class="min-w-0 flex-1 type-label-lg text-fg after:absolute after:inset-0 after:rounded-lg hover:text-fg-brand focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $serviceTranslation->title }}</a>
                            <x-ui.icon :name="$service->icon ?? 'code'" :size="20" class="shrink-0 text-icon-secondary" />
                        </li>
                    @endforeach
                </ul>
            @else
                <x-site.section-heading id="what-we-built" align="start" :eyebrow="__('Client feedback')" :heading="__('What the client says')" />
            @endif

            @if ($testimonials->isNotEmpty())
                <ul class="grid gap-6 {{ $testimonials->count() > 1 ? 'md:grid-cols-2' : '' }}" aria-label="{{ __('What the client says') }}">
                    @foreach ($testimonials as $testimonial)
                        <x-site.testimonial-card :testimonial="$testimonial" />
                    @endforeach
                </ul>
            @endif
        </x-site.section>
    @endif

    @if ($related->isNotEmpty())
        <x-site.section tone="surface" inner="">
            @include('portfolio._related', ['projects' => $related])
        </x-site.section>
    @endif

    <x-site.cta-band :title="__('Need a similar solution?')" :lead="__('We can adapt this approach to your business. Get a free preliminary estimate.')" :action="__('Request a similar solution')" />
</x-layouts.public>
