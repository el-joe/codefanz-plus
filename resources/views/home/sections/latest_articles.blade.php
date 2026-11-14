<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="latest_articles">
    <div class="flex flex-col items-start gap-5 md:flex-row md:items-end">
        <x-site.section-heading :id="$headingId" align="start" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        <x-ui.button :href="route('blog.index')" variant="secondary" class="shrink-0" arrow>{{ $copy['cta_label'] ?: __('Read the blog') }}</x-ui.button>
    </div>
    <ul data-reveal-stagger class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $post)
            @include('blog._card', ['post' => $post])
        @endforeach
    </ul>
</x-site.section>
