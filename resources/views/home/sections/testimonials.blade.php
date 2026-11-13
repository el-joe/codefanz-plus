<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="testimonials">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid items-start gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $testimonial)
            <x-site.testimonial-card :testimonial="$testimonial" heading-tag="h3" />
        @endforeach
    </ul>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><x-ui.button :href="route('testimonials.index')" variant="secondary" class="max-sm:w-full" arrow>{{ $copy['cta_label'] }}</x-ui.button></div>
    @endif
</x-site.section>
