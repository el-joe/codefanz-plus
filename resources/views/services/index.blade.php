<x-layouts.public
    :title="__('Software services for every stage of growth')"
    :description="__('Strategy, design, engineering and support — combine what you need, scale when you’re ready.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('services.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Services')" :title="__('Software services for every stage of growth')"
                      :lead="__('Strategy, design, engineering and support — combine what you need, scale when you’re ready.')"
                      :crumbs="[__('Services') => null]" />

    @if ($core->isEmpty() && $specialist->isEmpty())
        <section class="mx-auto max-w-7xl px-5 py-16 lg:px-20">
            <x-ui.empty-state :title="__('Our services are being updated')" :description="__('Please check back soon or contact us to discuss your project.')" />
        </section>
    @endif

    @if ($core->isNotEmpty())
        <section class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-16 lg:px-20 lg:py-20" aria-labelledby="core-services">
            <div class="flex flex-col gap-2">
                <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ __('Core services') }}</p>
                <h2 id="core-services" class="text-3xl font-bold text-fg">{{ __('What we build') }}</h2>
            </div>
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($core as $service)
                    @php($translation = $service->exactTranslation(app()->getLocale()))
                    <li class="group relative flex flex-col gap-3 rounded-xl border border-line bg-surface p-6 transition-shadow hover:shadow-lg">
                        <span class="flex size-11 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand"><x-ui.icon :name="$service->icon ?? 'code'" /></span>
                        <h3 class="text-lg font-semibold text-fg">
                            <a href="{{ route('services.show', ['slug' => $translation->slug]) }}" class="after:absolute after:inset-0">{{ $translation->title }}</a>
                        </h3>
                        @if ($translation->summary)
                            <p class="text-sm text-fg-secondary">{{ $translation->summary }}</p>
                        @endif
                        <span class="mt-auto inline-flex items-center gap-1.5 text-sm font-semibold text-fg-brand" aria-hidden="true">
                            {{ __('Explore service') }}
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($specialist->isNotEmpty())
        <section class="bg-subtle" aria-labelledby="specialist-services">
            <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-16 lg:px-20 lg:py-20">
                <div class="flex flex-col gap-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ __('Also available') }}</p>
                    <h2 id="specialist-services" class="text-3xl font-bold text-fg">{{ __('Specialist services') }}</h2>
                </div>
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($specialist as $service)
                        @php($translation = $service->exactTranslation(app()->getLocale()))
                        <li class="relative flex items-center gap-3 rounded-lg border border-line bg-surface p-4 hover:border-line-brand">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-accent-subtle text-fg-accent"><x-ui.icon :name="$service->icon ?? 'code'" :size="18" /></span>
                            <span class="flex min-w-0 flex-col">
                                <a href="{{ route('services.show', ['slug' => $translation->slug]) }}" class="font-semibold text-fg after:absolute after:inset-0">{{ $translation->title }}</a>
                                @if ($translation->summary)
                                    <span class="truncate text-xs text-fg-secondary">{{ $translation->summary }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <x-site.cta-band />
</x-layouts.public>
