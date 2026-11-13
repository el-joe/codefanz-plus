<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="industries">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $industry)
            <x-site.industry-card :industry="$industry" />
        @endforeach
    </ul>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><x-ui.button :href="route('industries.index')" variant="secondary" class="max-sm:w-full" arrow>{{ $copy['cta_label'] }}</x-ui.button></div>
    @endif
</x-site.section>
