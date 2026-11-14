{{--
    Figma "Section Header" (v2): eyebrow, H2 (v2 48/56, 32/40 on mobile; Arabic metrics with 0 tracking) and lead.
    align = center (960px column) | start (fills its row). Fades up on scroll (reveal.js).
--}}
@props(['eyebrow' => null, 'heading', 'subheading' => null, 'id' => null, 'align' => 'center', 'headingTag' => 'h2'])

<div data-reveal {{ $attributes->class(['flex flex-col gap-3', 'mx-auto max-w-[60rem] items-center text-center' => $align === 'center', 'min-w-0 flex-1 items-start text-start' => $align !== 'center']) }}>
    @if ($eyebrow)
        <p class="type-eyebrow text-fg-brand">{{ $eyebrow }}</p>
    @endif
    <{{ $headingTag }} @if ($id) id="{{ $id }}" @endif @class(['type-v2-h2-mobile text-v2-display lg:type-v2-h2', 'text-balance' => $align === 'center'])>{{ $heading }}</{{ $headingTag }}>
    @if ($subheading)
        <p class="type-body-lg text-fg-secondary lg:type-lead">{{ $subheading }}</p>
    @endif
</div>
