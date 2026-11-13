@php
    $locale = app()->getLocale();
    $reasons = [
        ['trending-up', __('Launch faster'), __('Start from a production-tested core.')],
        ['palette', __('Customized to you'), __('Workflows, branding and integrations.')],
        ['shield', __('Supported & updated'), __('Versioned releases with a public changelog.')],
    ];
@endphp

<x-layouts.public
    :title="__('Ready-made software, tailored to your business')"
    :description="__('Proven platforms we license, customize and support — launch in weeks instead of months.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('products.index', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="__('Software products')" :title="__('Ready-made software, tailored to your business')"
                      :lead="__('Proven platforms we license, customize and support — launch in weeks instead of months.')"
                      :crumbs="[__('Products') => null]" />

    <x-site.section tone="surface" inner="" aria-label="{{ __('Products') }}">
        @if ($products->isEmpty())
            <x-ui.empty-state :title="__('Our products are being updated')" :description="__('Please check back soon or contact us to discuss your project.')" />
        @else
            <ul class="grid gap-6 md:grid-cols-2">
                @foreach ($products as $product)
                    <x-site.product-card :product="$product" heading-tag="h2" />
                @endforeach
            </ul>
        @endif
    </x-site.section>

    <x-site.section tone="canvas" aria-labelledby="why-product">
        <x-site.section-heading id="why-product" :eyebrow="__('Why a product')" :heading="__('Faster than custom, more flexible than off-the-shelf')" />
        <ul class="grid gap-8 sm:grid-cols-3">
            @foreach ($reasons as [$icon, $title, $text])
                <li class="flex flex-col items-center gap-3 text-center">
                    <span class="flex size-12 items-center justify-center rounded-lg bg-brand-subtle text-icon-brand" aria-hidden="true"><x-ui.icon :name="$icon" :size="22" /></span>
                    <h3 class="type-h5 text-fg">{{ $title }}</h3>
                    <p class="type-body-sm text-fg-secondary">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </x-site.section>

    <x-site.cta-band :title="__('Not sure which product fits?')" :lead="__('Talk to our team — we’ll recommend a product or a custom build.')" :action="__('Contact sales')" />
</x-layouts.public>
