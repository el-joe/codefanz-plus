@php($icons = ['shield', 'code', 'users', 'sparkles', 'trending-up', 'layers', 'heart-pulse', 'globe'])
<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="about_values">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($copy['items'] as $value)
            <li class="flex flex-col items-start gap-3">
                <span class="flex size-11 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand" aria-hidden="true"><x-ui.icon :name="$icons[$loop->index % count($icons)]" :size="22" /></span>
                <h3 class="type-h5 text-fg">{{ $value['title'] }}</h3>
                @if (filled($value['text'] ?? null))
                    <p class="type-body-sm text-fg-secondary">{{ $value['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</x-site.section>
