{{-- Figma "Icon/arrow-right" (1.75 stroke). Points to the reading direction: mirrored in RTL. --}}
@props(['size' => 20])

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'shrink-0 rtl:-scale-x-100']) }}><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
