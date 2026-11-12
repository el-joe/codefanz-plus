<section class="bg-subtle" aria-labelledby="{{ $headingId }}" data-section="faq">
    <div class="mx-auto flex max-w-3xl flex-col gap-10 px-5 py-16 lg:py-24">
        <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        <div class="flex flex-col gap-3">
            @foreach ($data as $faq)
                <x-site.faq-item :translation="$faq->exactTranslation(app()->getLocale())" :open="$loop->first" />
            @endforeach
        </div>
        @if ($copy['cta_label'])
            <div class="flex justify-center"><a href="{{ route('faq.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ $copy['cta_label'] }}</a></div>
        @endif
    </div>
</section>
@include('faq._schema', ['faqs' => $data])
