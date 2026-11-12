<section class="bg-subtle" aria-labelledby="{{ $headingId }}" data-section="products">
    <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-16 lg:px-20 lg:py-24">
        <x-site.section-heading :id="$headingId" :eyebrow="$copy['eyebrow']" :heading="$copy['heading']" :subheading="$copy['subheading']" />
        <ul class="grid gap-5 md:grid-cols-2">
            @foreach ($data as $product)
                @php($translation = $product->exactTranslation(app()->getLocale()))
                <li class="relative flex flex-col gap-4 rounded-xl border border-line bg-surface p-6">
                    <div class="flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand text-fg-on-brand"><x-ui.icon :name="$product->icon ?? 'layers'" /></span>
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <h3 class="flex flex-wrap items-center gap-2 text-lg font-semibold text-fg">
                                <a href="{{ route('products.show', ['slug' => $translation->slug]) }}" class="after:absolute after:inset-0 hover:text-fg-brand">{{ $translation->name }}</a>
                                <x-ui.badge :tone="$product->availability->tone()">{{ $product->availability->label() }}</x-ui.badge>
                            </h3>
                            @if ($translation->tagline)
                                <p class="text-sm text-fg-secondary">{{ $translation->tagline }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($features = array_slice($translation->filledFeatures(), 0, 3))
                        <ul class="flex flex-col gap-2 text-sm text-fg-secondary">
                            @foreach ($features as $feature)
                                <li class="flex items-start gap-2">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="mt-0.5 shrink-0 text-fg-success"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                                    {{ $feature['title'] }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($copy['cta_label'])
            <div class="flex justify-center"><x-ui.button :href="route('products.index')" variant="secondary">{{ $copy['cta_label'] }}</x-ui.button></div>
        @endif
    </div>
</section>
