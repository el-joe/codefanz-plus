<x-site.section :tone="$tone" aria-labelledby="{{ $headingId }}" data-section="products">
    <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
    <ul class="grid grid-cols-1 gap-6 md:grid-cols-2">
        @foreach ($data as $product)
            <x-site.product-card :product="$product" />
        @endforeach
    </ul>
    @if ($copy['cta_label'])
        <div class="flex justify-center"><x-ui.button :href="route('products.index')" variant="secondary" class="max-sm:w-full" arrow>{{ $copy['cta_label'] }}</x-ui.button></div>
    @endif
</x-site.section>
