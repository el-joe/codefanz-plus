<section class="bg-subtle" aria-labelledby="{{ $headingId }}" data-section="process">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24">
        <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        <ol class="grid gap-8 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5'][min(5, max(1, count($copy['items'])))] }}">
            @foreach ($copy['items'] as $step)
                <li class="flex flex-col gap-3">
                    <span class="flex size-11 items-center justify-center rounded-full bg-brand-subtle text-sm font-bold text-fg-brand" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="text-lg font-semibold text-fg">{{ $step['title'] }}</h3>
                    @if (filled($step['text'] ?? null))
                        <p class="text-sm text-fg-secondary">{{ $step['text'] }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</section>
