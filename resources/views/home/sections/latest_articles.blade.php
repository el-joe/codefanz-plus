<section class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24" aria-labelledby="{{ $headingId }}" data-section="latest_articles">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($data as $post)
            @include('blog._card', ['post' => $post])
        @endforeach
    </ul>
    <div class="flex justify-center"><a href="{{ route('blog.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ $copy['cta_label'] ?: __('Read the blog') }}</a></div>
</section>
