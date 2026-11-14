{{--
    Design v2 static image (App\Services\SiteImages): <picture> with AVIF then WebP srcsets, a JPEG <img> fallback,
    intrinsic width/height (no layout shift), lazy + async decoding by default; `eager` = hero (eager, fetchpriority high).
    Decorative by default (alt=""); `described` uses the localized alt text from the image map (docs/DESIGN_V2.md §5).
--}}
@props(['name', 'sizes' => '100vw', 'eager' => false, 'described' => false])

@php
    $images = \App\Services\SiteImages::class;
    $definition = $images::definition($name);
    $fallback = $images::fallbackWidth($name);
@endphp

<picture>
    <source type="image/avif" srcset="{{ $images::srcset($name, 'avif') }}" sizes="{{ $sizes }}">
    <source type="image/webp" srcset="{{ $images::srcset($name, 'webp') }}" sizes="{{ $sizes }}">
    <img src="{{ $images::url($name, $fallback, 'jpg') }}" width="{{ $definition['width'] }}" height="{{ $definition['height'] }}"
         alt="{{ $described ? $images::alt($name) : '' }}" decoding="{{ $eager ? 'sync' : 'async' }}" loading="{{ $eager ? 'eager' : 'lazy' }}" @if ($eager) fetchpriority="high" @endif
         {{ $attributes }}>
</picture>
