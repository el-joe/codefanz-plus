{{-- Figma "Card/Product" (v2: radius 24, hairline, card shadow, brand → violet tile with glow): icon tile, name + tagline (availability badge unless generally available), three key features and the demo (contact form, topic=demo) / learn-more actions. Expects translations loaded. --}}
@props(['product', 'headingTag' => 'h3'])

@php
    $translation = $product->exactTranslation(app()->getLocale());
    $url = route('products.show', ['slug' => $translation->slug]);
@endphp

<li {{ $attributes->merge(['class' => 'flex flex-col items-start gap-5 rounded-3xl border border-v2-hairline bg-surface p-6 shadow-v2-card transition duration-200 ease-out hover:-translate-y-1 hover:border-brand-600/30 hover:shadow-v2-lift sm:p-8']) }}>
    <div class="flex w-full items-center gap-4">
        <span class="flex size-14 shrink-0 items-center justify-center rounded-lg tile-v2-product text-fg-on-brand" aria-hidden="true"><x-ui.icon :name="$product->icon ?? 'layers'" :size="28" /></span>
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <{{ $headingTag }} class="flex flex-wrap items-center gap-x-2 gap-y-1 type-h4 text-fg">
                <a href="{{ $url }}" class="hover:text-fg-brand">{{ $translation->name }}</a>
                @if ($product->availability !== \App\Enums\ProductAvailability::Available)
                    <x-ui.badge :tone="$product->availability->tone()">{{ $product->availability->label() }}</x-ui.badge>
                @endif
            </{{ $headingTag }}>
            @if ($translation->tagline)
                <p class="type-body-sm text-fg-secondary">{{ $translation->tagline }}</p>
            @endif
        </div>
    </div>
    @if ($features = array_slice($translation->filledFeatures(), 0, 3))
        <ul class="flex flex-col gap-2.5">
            @foreach ($features as $feature)
                <li class="flex items-start gap-2.5 type-body-md text-fg">
                    <x-ui.check-circle class="mt-[3px]" />
                    {{ $feature['title'] }}
                </li>
            @endforeach
        </ul>
    @endif
    <div class="mt-auto flex flex-wrap items-center gap-3">
        @if ($product->demo_enabled)
            <x-ui.button :href="route('contact', ['topic' => 'demo', 'ref' => $translation->name])">{{ __('Request a demo') }}</x-ui.button>
        @endif
        <x-ui.button :href="$url" variant="ghost" arrow>{{ __('Learn more') }}<span class="sr-only"> — {{ $translation->name }}</span></x-ui.button>
    </div>
</li>
