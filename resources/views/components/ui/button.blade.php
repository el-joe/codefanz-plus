{{-- Figma "Button": SM 32 / MD 40 / LG 48 high, radius-md. `arrow` appends the trailing arrow (mirrored in RTL). --}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit', 'arrow' => false])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md transition-colors focus-visible:outline-2 disabled:cursor-not-allowed disabled:border-transparent disabled:bg-disabled disabled:text-fg-disabled';
    $sizes = [
        'sm' => 'px-3 py-1.5 type-label-md',
        'md' => 'px-4 py-2.5 type-label-md',
        'lg' => 'px-6 py-3 type-label-lg',
    ];
    $variants = [
        'primary' => 'bg-brand text-fg-on-brand hover:bg-brand-hover',
        'secondary' => 'border border-line-strong bg-surface text-fg hover:border-line-brand hover:bg-subtle',
        'ghost' => 'text-fg-brand hover:bg-brand-subtle',
        'danger' => 'bg-danger text-fg-on-brand hover:bg-danger-hover',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}@if ($arrow)<x-ui.arrow :size="$size === 'sm' ? 16 : 20" />@endif</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}@if ($arrow)<x-ui.arrow :size="$size === 'sm' ? 16 : 20" />@endif</button>
@endif
