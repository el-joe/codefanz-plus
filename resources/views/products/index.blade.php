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

    <section class="mx-auto max-w-7xl px-5 py-14 lg:px-20 lg:py-20" aria-label="{{ __('Products') }}">
        @if ($products->isEmpty())
            <x-ui.empty-state :title="__('Our products are being updated')" :description="__('Please check back soon or contact us to discuss your project.')" />
        @else
            <ul class="grid gap-5 md:grid-cols-2">
                @foreach ($products as $product)
                    @php($translation = $product->exactTranslation($locale))
                    @php($url = route('products.show', ['slug' => $translation->slug]))
                    <li class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-6">
                        <div class="flex items-start gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-brand text-fg-on-brand"><x-ui.icon :name="$product->icon ?? 'layers'" /></span>
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <h2 class="flex flex-wrap items-center gap-2 text-lg font-semibold text-fg">
                                    <a href="{{ $url }}" class="hover:text-fg-brand">{{ $translation->name }}</a>
                                    <x-ui.badge :tone="$product->availability->tone()">{{ $product->availability->label() }}</x-ui.badge>
                                </h2>
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
                        <div class="mt-auto flex flex-wrap items-center gap-4">
                            @if ($product->demo_enabled)
                                <x-ui.button size="sm" :href="route('contact', ['topic' => 'demo', 'ref' => $translation->name])">{{ __('Request a demo') }}</x-ui.button>
                            @endif
                            <a href="{{ $url }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-fg-brand hover:underline">
                                {{ __('Learn more') }}<span class="sr-only">: {{ $translation->name }}</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="bg-subtle" aria-labelledby="why-product">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-10 px-5 py-16 text-center lg:px-20 lg:py-20">
            <div class="flex max-w-2xl flex-col gap-2">
                <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ __('Why a product') }}</p>
                <h2 id="why-product" class="text-3xl font-bold text-fg">{{ __('Faster than custom, more flexible than off-the-shelf') }}</h2>
            </div>
            <ul class="grid w-full gap-8 sm:grid-cols-3">
                @foreach ($reasons as [$icon, $title, $text])
                    <li class="flex flex-col items-center gap-2">
                        <span class="flex size-11 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand"><x-ui.icon :name="$icon" /></span>
                        <h3 class="font-semibold text-fg">{{ $title }}</h3>
                        <p class="text-sm text-fg-secondary">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <x-site.cta-band :title="__('Not sure which product fits?')" :lead="__('Talk to our team — we’ll recommend a product or a custom build.')" :action="__('Contact sales')" />
</x-layouts.public>
