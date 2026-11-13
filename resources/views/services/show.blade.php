@php
    $locale = app()->getLocale();
    $cover = $service->coverImage();
    $parentTranslation = $service->parent?->exactTranslation($locale);
    $crumbs = [__('Services') => route('services.index')];
    if ($parentTranslation) {
        $crumbs[$parentTranslation->title] = route('services.show', ['slug' => $parentTranslation->slug]);
    }
    $crumbs[$translation->title] = null;
    $quoteUrl = route('quote', ['service' => $translation->slug]);
@endphp

<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: ($translation->summary ?: $translation->title)"
    :image="$cover"
    :seoable="$service"
    :alternates="$service->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('services.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-slot:head>
        @php($structuredData = app(\App\Services\Seo\StructuredData::class))
        {{ $structuredData->script($structuredData->service($translation->title, $translation->summary, route('services.show', ['slug' => $translation->slug]), $cover?->largestVariant('webp') ? url($cover->largestVariant('webp')->url()) : null, $service->tier->label())) }}
    </x-slot:head>

    {{-- Figma "Service Detail" hero: filled brand icon tile, title, lead and the two actions. --}}
    <x-site.page-hero :title="$translation->title" :lead="$translation->summary" :crumbs="$crumbs">
        <x-slot:before>
            <span class="flex size-14 items-center justify-center rounded-lg bg-brand text-fg-on-brand" aria-hidden="true"><x-ui.icon :name="$service->icon ?? 'code'" :size="28" /></span>
        </x-slot:before>
        <div class="flex flex-wrap gap-3">
            <x-ui.button :href="$quoteUrl" size="lg" arrow>{{ __('Request a quote') }}</x-ui.button>
            @if ($projects->isNotEmpty())
                <x-ui.button href="#related-work" variant="secondary" size="lg">{{ __('See related work') }}</x-ui.button>
            @endif
        </div>
    </x-site.page-hero>

    <x-site.section inner="grid gap-12 lg:grid-cols-[minmax(0,1fr)_23.75rem] lg:items-start lg:gap-16">
        <div class="flex min-w-0 flex-col gap-12">
            @if ($cover)
                <x-media.picture :media="$cover" sizes="(min-width: 1024px) 836px, 100vw" :eager="true" class="w-full rounded-xl border border-line" />
            @endif

            @if (trim($body) !== '')
                <div class="flex flex-col gap-4 type-body-lg text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-4 [&_h2]:type-h3 [&_h2]:text-fg [&_h3]:type-h4 [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_strong]:text-fg [&_ul]:list-disc [&_ul]:ps-6">
                    {!! $body !!}
                </div>
            @endif

            @if ($service->features->isNotEmpty())
                <section aria-labelledby="deliverables" class="flex flex-col gap-6">
                    <h2 id="deliverables" class="type-h3 text-fg">{{ __('What you get') }}</h2>
                    <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        @foreach ($service->features as $feature)
                            @continue(! ($featureTranslation = $feature->exactTranslation($locale)))
                            <li class="flex items-start gap-3">
                                <x-ui.check-circle :size="22" class="mt-px" />
                                <span class="flex flex-col gap-1">
                                    <span class="type-label-lg text-fg">{{ $featureTranslation->title }}</span>
                                    @if ($featureTranslation->description)
                                        <span class="type-body-sm text-fg-secondary">{{ $featureTranslation->description }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($service->technologies->isNotEmpty())
                <section aria-labelledby="tech-stack" class="flex flex-col gap-4">
                    <h2 id="tech-stack" class="type-h4 text-fg">{{ __('Technologies we use') }}</h2>
                    <ul class="flex flex-wrap gap-2.5">
                        @foreach ($service->technologies as $technology)
                            <x-site.tech-chip :technology="$technology" />
                        @endforeach
                    </ul>
                    <p class="type-body-xs text-fg-tertiary">
                        {{ __('Levels show whether a technology is verified in published projects, an area of expertise, or supported on request.') }}
                        <a href="{{ route('technologies.index') }}" class="font-semibold text-fg-brand hover:underline">{{ __('See all technologies') }}</a>
                    </p>
                </section>
            @endif

            @if ($service->children->isNotEmpty())
                <section aria-labelledby="sub-services" class="flex flex-col gap-4">
                    <h2 id="sub-services" class="type-h4 text-fg">{{ __('Related services') }}</h2>
                    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($service->children as $child)
                            @php($childTranslation = $child->exactTranslation($locale))
                            <x-site.tile-card :icon="$child->icon ?? 'code'" :title="$childTranslation->title" :text="$childTranslation->summary"
                                              :href="route('services.show', ['slug' => $childTranslation->slug])" />
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="lg:sticky lg:top-24" aria-labelledby="quote-card">
            <div class="flex flex-col gap-4 rounded-xl border border-line bg-surface px-6 py-7 shadow-lg">
                <h2 id="quote-card" class="type-h4 text-fg">{{ __('Get a free estimate') }}</h2>
                <p class="type-body-sm text-fg-secondary">{{ __('Tell us about your project and get a free preliminary estimate within 48 hours. No payment required.') }}</p>
                <x-ui.button :href="$quoteUrl" size="lg" class="w-full" arrow>{{ __('Request a quote') }}</x-ui.button>
                <p class="flex items-center gap-2 type-body-xs text-fg-tertiary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-success"><path d="M12 3 5 6v5c0 4.5 3 8.3 7 9.5 4-1.2 7-5 7-9.5V6z"/><path d="m9 12 2 2 4-4"/></svg>
                    {{ __('Free preliminary estimate · No payment required') }}
                </p>
                <a href="{{ route('services.index') }}" class="type-label-md text-fg-brand hover:underline">{{ __('Browse all services') }}</a>
            </div>
        </aside>
    </x-site.section>

    @if ($projects->isNotEmpty())
        <x-site.section tone="canvas" id="related-work" inner="">
            @include('portfolio._related', ['projects' => $projects, 'eyebrow' => __('Related work'), 'title' => __('Projects delivered with this service')])
        </x-site.section>
    @endif

    @if ($service->faqs->isNotEmpty())
        <x-site.section aria-labelledby="faq">
            <x-site.section-heading id="faq" align="start" :eyebrow="__('FAQ')" :heading="__('Frequently asked questions')" />
            <div class="flex flex-col gap-3">
                @foreach ($service->faqs as $faq)
                    @continue(! ($faqTranslation = $faq->exactTranslation($locale)))
                    <x-site.faq-item :question="$faqTranslation->question" :open="$loop->first">@if ($faqTranslation->answer)<span class="whitespace-pre-line">{{ $faqTranslation->answer }}</span>@endif</x-site.faq-item>
                @endforeach
            </div>
        </x-site.section>
    @endif

    <x-site.cta-band :tone="$service->faqs->isEmpty() && $projects->isNotEmpty() ? 'surface' : 'canvas'" />
</x-layouts.public>
