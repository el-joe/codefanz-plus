{{--
    Figma "Button": SM 32 / MD 40 / LG 48 high, radius-md. `arrow` appends the trailing arrow (mirrored in RTL).
    Design v2 (public site only, `site:` variant): pill radius; primary gets the brand glow and lifts 1px on hover,
    secondary a hairline border with the brand border at 35% on hover; the arrow nudges 4px toward the inline end.
--}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'submit', 'arrow' => false])

@php
    $base = 'group/button inline-flex items-center justify-center gap-2 rounded-md transition-colors focus-visible:outline-2 disabled:cursor-not-allowed disabled:border-transparent disabled:bg-disabled disabled:text-fg-disabled site:rounded-full site:transition site:duration-200 site:ease-out site:focus-visible:outline-3';
    $sizes = [
        'sm' => 'px-3 py-1.5 type-label-md',
        'md' => 'px-4 py-2.5 type-label-md',
        'lg' => 'px-6 py-3 type-label-lg',
    ];
    $variants = [
        'primary' => 'bg-brand text-fg-on-brand hover:bg-brand-hover site:shadow-v2-glow site:hover:-translate-y-px site:disabled:translate-y-0 site:disabled:shadow-none',
        'secondary' => 'border border-line-strong bg-surface text-fg hover:border-line-brand hover:bg-subtle site:hover:border-brand-600/35 site:hover:bg-ink-50',
        'ghost' => 'text-fg-brand hover:bg-brand-subtle',
        'danger' => 'bg-danger text-fg-on-brand hover:bg-danger-hover',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}@if ($arrow)<x-ui.arrow :size="$size === 'sm' ? 16 : 20" class="site:transition-transform site:duration-200 site:group-hover/button:translate-x-1 site:rtl:group-hover/button:-translate-x-1" />@endif</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}@if ($arrow)<x-ui.arrow :size="$size === 'sm' ? 16 : 20" class="site:transition-transform site:duration-200 site:group-hover/button:translate-x-1 site:rtl:group-hover/button:-translate-x-1" />@endif</button>
@endif
