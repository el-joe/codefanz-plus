<section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="testimonials">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $testimonial)
            <x-site.testimonial-card :testimonial="$testimonial" heading-tag="h3" />
        @endforeach
    </ul>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><a href="{{ route('testimonials.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ $copy['cta_label'] }}</a></div>
    @endif
</section>
