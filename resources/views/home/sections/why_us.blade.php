@php
    // Figma "Why CodeFanz" point icons, in order; reasons have no icon field, so they cycle through this set.
    $reasonIcons = [
        '<path d="M12 3 5 6v5c0 4.5 3 8.3 7 9.5 4-1.2 7-5 7-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
        '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/>',
        '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/>',
    ];
@endphp
<x-site.section :tone="$tone" inner="grid gap-10 lg:grid-cols-[1fr_27.5rem] lg:items-start lg:gap-16" aria-labelledby="{{ $headingId }}" data-section="why_us">
    <div class="flex flex-col gap-8">
        <x-site.section-heading :id="$headingId" align="start" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        @if ($copy['items'] !== [])
            <ul class="grid gap-6 sm:grid-cols-2">
                @foreach ($copy['items'] as $reason)
                    <li class="flex flex-col items-start gap-3">
                        <span class="flex size-11 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $reasonIcons[$loop->index % count($reasonIcons)] !!}</svg>
                        </span>
                        <h3 class="type-h5 text-fg">{{ $reason['title'] }}</h3>
                        @if (filled($reason['text'] ?? null))
                            <p class="type-body-sm text-fg-secondary">{{ $reason['text'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @if ($copy['points'] !== [])
        <div class="dark flex flex-col gap-5 rounded-[1.5rem] bg-[linear-gradient(140deg,var(--color-brand-800)_14%,var(--color-ink-950)_86%)] px-6 py-8 text-fg sm:px-8 sm:py-10">
            <h3 class="type-h4 text-fg">{{ __('Our delivery promise') }}</h3>
            <ul class="flex flex-col gap-5">
                @foreach ($copy['points'] as $point)
                    <li class="flex items-start gap-2.5 type-body-md text-fg"><x-ui.check-circle class="mt-[3px]" />{{ $point }}</li>
                @endforeach
            </ul>
            @if ($copy['cta_label'])
                <x-ui.button :href="$section->ctaUrl() ?? $contactHref" size="lg" class="w-full" arrow>{{ $copy['cta_label'] }}</x-ui.button>
            @endif
        </div>
    @endif
</x-site.section>
