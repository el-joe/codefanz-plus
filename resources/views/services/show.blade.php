@php
    $locale = app()->getLocale();
    $cover = $service->coverImage();
    $parentTranslation = $service->parent?->exactTranslation($locale);
    $crumbs = [__('Services') => route('services.index')];
    if ($parentTranslation) {
        $crumbs[$parentTranslation->title] = route('services.show', ['slug' => $parentTranslation->slug]);
    }
    $crumbs[$translation->title] = null;
@endphp

<x-layouts.public
    :title="$translation->seo_title ?: $translation->title"
    :description="$translation->seo_description ?: $translation->summary"
    :alternates="$service->translations->mapWithKeys(fn ($alt) => [$alt->locale => route('services.show', ['locale' => $alt->locale, 'slug' => $alt->slug])])->all()">
    <x-site.page-hero :eyebrow="$service->tier->label()" :title="$translation->title" :lead="$translation->summary"
                      :crumbs="$crumbs">
        <x-slot:before>
            @if ($service->icon)
                <span class="flex size-12 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand"><x-ui.icon :name="$service->icon" :size="24" /></span>
            @endif
        </x-slot:before>
    </x-site.page-hero>

    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 lg:grid-cols-[1fr_340px] lg:px-20 lg:py-20">
        <div class="flex min-w-0 flex-col gap-14">
            @if ($cover)
                <x-media.picture :media="$cover" sizes="(min-width: 1024px) 760px, 100vw" :eager="true" class="w-full rounded-xl border border-line" />
            @endif

            @if (trim($body) !== '')
                <div class="flex flex-col gap-4 text-lg leading-relaxed text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_h2]:mt-6 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-fg [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-fg [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6">
                    {!! $body !!}
                </div>
            @endif

            @if ($service->features->isNotEmpty())
                <section aria-labelledby="deliverables" class="flex flex-col gap-6">
                    <h2 id="deliverables" class="text-2xl font-bold text-fg">{{ __('What you get') }}</h2>
                    <ul class="grid gap-4 sm:grid-cols-2">
                        @foreach ($service->features as $feature)
                            @continue(! ($featureTranslation = $feature->exactTranslation($locale)))
                            <li class="flex gap-3 rounded-lg border border-line bg-surface p-4">
                                <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-accent-subtle text-fg-accent" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 5 5 9-10"/></svg>
                                </span>
                                <span class="flex flex-col gap-1">
                                    <span class="font-semibold text-fg">{{ $featureTranslation->title }}</span>
                                    @if ($featureTranslation->description)
                                        <span class="text-sm text-fg-secondary">{{ $featureTranslation->description }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($service->children->isNotEmpty())
                <section aria-labelledby="sub-services" class="flex flex-col gap-6">
                    <h2 id="sub-services" class="text-2xl font-bold text-fg">{{ __('Related services') }}</h2>
                    <ul class="grid gap-4 sm:grid-cols-2">
                        @foreach ($service->children as $child)
                            @php($childTranslation = $child->exactTranslation($locale))
                            <li class="relative flex items-center gap-3 rounded-lg border border-line bg-surface p-4 hover:border-line-brand">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-brand-subtle text-fg-brand"><x-ui.icon :name="$child->icon ?? 'code'" :size="18" /></span>
                                <a href="{{ route('services.show', ['slug' => $childTranslation->slug]) }}" class="font-semibold text-fg after:absolute after:inset-0">{{ $childTranslation->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($service->technologies->isNotEmpty())
                <section aria-labelledby="tech-stack" class="flex flex-col gap-4">
                    <h2 id="tech-stack" class="text-2xl font-bold text-fg">{{ __('Tech stack') }}</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($service->technologies as $technology)
                            <x-site.tech-chip :technology="$technology" />
                        @endforeach
                    </ul>
                    <p class="text-sm text-fg-tertiary">
                        {{ __('Levels show whether a technology is verified in published projects, an area of expertise, or supported on request.') }}
                        <a href="{{ route('technologies.index') }}" class="font-semibold text-fg-brand hover:underline">{{ __('See all technologies') }}</a>
                    </p>
                </section>
            @endif

            @if ($service->faqs->isNotEmpty())
                <section aria-labelledby="faq" class="flex flex-col gap-6">
                    <h2 id="faq" class="text-2xl font-bold text-fg">{{ __('Frequently asked questions') }}</h2>
                    <div class="flex flex-col gap-3">
                        @foreach ($service->faqs as $faq)
                            @continue(! ($faqTranslation = $faq->exactTranslation($locale)))
                            <details class="group rounded-lg border border-line bg-surface open:border-line-brand">
                                <summary class="flex cursor-pointer list-none items-center gap-3 p-4 font-semibold text-fg [&::-webkit-details-marker]:hidden">
                                    <span class="flex-1">{{ $faqTranslation->question }}</span>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="shrink-0 text-fg-tertiary transition-transform group-open:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                @if ($faqTranslation->answer)
                                    <p class="whitespace-pre-line px-4 pb-4 text-fg-secondary">{{ $faqTranslation->answer }}</p>
                                @endif
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start" aria-labelledby="quote-card">
            <div class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-6 shadow-md">
                <h2 id="quote-card" class="text-lg font-bold text-fg">{{ __('Request a quote') }}</h2>
                <p class="text-sm text-fg-secondary">{{ __('Tell us about your project and get a free preliminary estimate within 48 hours. No payment required.') }}</p>
                <x-ui.button :href="'mailto:'.(app(\App\Services\Settings::class)->get('contact', 'email') ?: 'hello@codefanz.com').'?subject='.rawurlencode($translation->title)" size="lg">{{ __('Start Your Project') }}</x-ui.button>
                <a href="{{ route('services.index') }}" class="text-center text-sm font-semibold text-fg-brand hover:underline">{{ __('Browse all services') }}</a>
            </div>
        </aside>
    </div>

    <x-site.cta-band />
</x-layouts.public>
