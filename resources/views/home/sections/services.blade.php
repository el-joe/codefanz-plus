<section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="services">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $service)
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
                    {{ __('Learn more') }}
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </span>
            </li>
        @endforeach
    </ul>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><x-ui.button :href="route('services.index')" variant="secondary">{{ $copy['cta_label'] }}</x-ui.button></div>
    @endif
</section>
