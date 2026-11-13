<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="services">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $service)
            <x-site.service-card :service="$service" />
        @endforeach
    </ul>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><x-ui.button :href="route('services.index')" variant="secondary" class="max-sm:w-full" arrow>{{ $copy['cta_label'] }}</x-ui.button></div>
    @endif
</x-site.section>
