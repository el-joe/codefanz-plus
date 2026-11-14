<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="featured_projects">
    <div class="flex flex-col items-start gap-5 md:flex-row md:items-end">
        <x-site.section-heading :id="$headingId" align="start" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        @if ($copy['cta_label'])
            <x-ui.button :href="route('portfolio.index')" variant="secondary" class="shrink-0" arrow>{{ $copy['cta_label'] }}</x-ui.button>
        @endif
    </div>
    <ul data-reveal-stagger class="grid grid-cols-1 items-start gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $project)
            @include('portfolio._card', ['project' => $project])
        @endforeach
    </ul>
</x-site.section>
