<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="faq">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <div class="mx-auto flex w-full max-w-[50rem] flex-col gap-3">
        @foreach ($data as $faq)
            <x-site.faq-item :translation="$faq->exactTranslation(app()->getLocale())" :open="$loop->first" />
        @endforeach
    </div>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><x-ui.button :href="route('faq.index')" variant="ghost" arrow>{{ $copy['cta_label'] }}</x-ui.button></div>
    @endif
</x-site.section>
@include('faq._schema', ['faqs' => $data])
