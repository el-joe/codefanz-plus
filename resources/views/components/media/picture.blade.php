@props(['media', 'sizes' => '100vw', 'alt' => null, 'eager' => false])

@php
    $fallback = $media->largestVariant('webp') ?? $media->largestVariant('gif');
    $srcset = fn (string $format) => $media->variantsFor($format)->map(fn ($variant) => $variant->url().' '.$variant->width.'w')->implode(', ');
@endphp

@if ($fallback)
    <picture>
        @if ($media->variantsFor('avif')->isNotEmpty())
            <source type="image/avif" srcset="{{ $srcset('avif') }}" sizes="{{ $sizes }}">
        @endif
        @if ($media->variantsFor('webp')->isNotEmpty())
            <source type="image/webp" srcset="{{ $srcset('webp') }}" sizes="{{ $sizes }}">
        @endif
        <img src="{{ $fallback->url() }}" width="{{ $fallback->width }}" height="{{ $fallback->height }}"
             alt="{{ $alt ?? $media->altText() }}" decoding="async" loading="{{ $eager ? 'eager' : 'lazy' }}"
             {{ $attributes->merge(['class' => 'h-auto max-w-full']) }}>
    </picture>
@endif
