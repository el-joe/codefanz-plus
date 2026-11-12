<section class="bg-subtle" aria-labelledby="{{ $headingId }}" data-section="technologies">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24">
        <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        <ul class="flex flex-wrap justify-center gap-2.5">
            @foreach ($data as $technology)
                <x-site.tech-chip :technology="$technology" :href="route('portfolio.index', ['technology' => $technology->key])" />
            @endforeach
        </ul>
        @if ($copy['cta_label'])
            <div class="flex justify-center"><a href="{{ route('technologies.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ $copy['cta_label'] }}</a></div>
        @endif
    </div>
</section>
