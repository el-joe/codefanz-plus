@php($icons = ['palette', 'code', 'globe', 'shield', 'sparkles', 'briefcase', 'cloud', 'database'])
<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="about_team">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($copy['items'] as $discipline)
            <x-site.tile-card :icon="$icons[$loop->index % count($icons)]" :title="$discipline['title']" :text="$discipline['text'] ?? null" />
        @endforeach
    </ul>
</x-site.section>
