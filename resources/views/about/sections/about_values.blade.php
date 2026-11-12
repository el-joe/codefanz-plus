@php($icons = ['shield', 'code', 'users', 'sparkles', 'trending-up', 'layers', 'heart-pulse', 'globe'])
<section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="about_values">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($copy['items'] as $value)
            <li class="flex flex-col gap-2">
                <span class="flex size-10 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand"><x-ui.icon :name="$icons[$loop->index % count($icons)]" /></span>
                <h3 class="text-base font-semibold text-fg">{{ $value['title'] }}</h3>
                @if (filled($value['text'] ?? null))
                    <p class="text-sm text-fg-secondary">{{ $value['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</section>
