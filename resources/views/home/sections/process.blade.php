<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="process">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ol data-reveal-stagger class="grid gap-6 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min(5, max(1, count($copy['items'])))] }}">
        @foreach ($copy['items'] as $step)
            <li class="flex flex-col items-start gap-3">
                <span class="flex size-12 items-center justify-center rounded-full tile-v2 font-mono text-[0.8125rem] leading-4 font-medium text-fg-brand" aria-hidden="true" dir="ltr">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <h3 class="type-h5 text-fg">{{ $step['title'] }}</h3>
                @if (filled($step['text'] ?? null))
                    <p class="type-body-sm text-fg-secondary">{{ $step['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
</x-site.section>
