<section class="mx-auto grid max-w-7xl gap-10 px-5 py-16 lg:grid-cols-[1.4fr_1fr] lg:items-center lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="why_us">
    <div class="flex flex-col gap-8">
        <x-site.section-heading :id="$headingId" align="start" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        @if ($copy['items'] !== [])
            <ul class="grid gap-6 sm:grid-cols-2">
                @foreach ($copy['items'] as $reason)
                    <li class="flex flex-col gap-2">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="m5 12 4 4 10-10"/></svg>
                        </span>
                        <h3 class="text-base font-semibold text-fg">{{ $reason['title'] }}</h3>
                        @if (filled($reason['text'] ?? null))
                            <p class="text-sm text-fg-secondary">{{ $reason['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @if ($copy['points'] !== [])
        <div class="flex flex-col gap-5 rounded-2xl bg-[linear-gradient(140deg,var(--color-brand-800),var(--color-ink-950))] p-8 text-white">
            <h3 class="text-xl font-semibold">{{ __('Our delivery promise') }}</h3>
            <ul class="flex flex-col gap-3 text-sm text-white/85">
                @foreach ($copy['points'] as $point)
                    <li class="flex items-start gap-2.5">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="mt-0.5 shrink-0 text-accent-300"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>
            @if ($copy['cta_label'])
                <a href="{{ $section->ctaUrl() ?? $contactHref }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-brand px-5 py-3 text-sm font-semibold text-fg-on-brand hover:bg-brand-hover">
                    {{ $copy['cta_label'] }}
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            @endif
        </div>
    @endif
</section>
