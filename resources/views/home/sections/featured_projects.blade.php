<section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="featured_projects">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <x-site.section-heading :id="$headingId" align="start" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        @if ($copy['cta_label'])
            <x-ui.button :href="route('portfolio.index')" variant="secondary">{{ $copy['cta_label'] }}</x-ui.button>
        @endif
    </div>
    <ul class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $project)
            @include('portfolio._card', ['project' => $project])
        @endforeach
    </ul>
</section>
