@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit'])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md font-semibold transition-colors focus-visible:outline-2 disabled:cursor-not-allowed disabled:bg-disabled disabled:text-fg-disabled';
    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2.5 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];
    $variants = [
        'primary' => 'bg-brand text-fg-on-brand hover:bg-brand-hover',
        'secondary' => 'border border-line-strong bg-surface text-fg hover:bg-subtle',
        'ghost' => 'text-fg-brand hover:bg-brand-subtle',
        'danger' => 'bg-danger text-fg-on-brand hover:bg-danger-hover',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
