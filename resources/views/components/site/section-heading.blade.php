{{-- Figma "Section Header": overline, H2 and lead. align = center (720px column) | start (fills its row). --}}
@props(['eyebrow' => null, 'heading', 'subheading' => null, 'id' => null, 'align' => 'center', 'headingTag' => 'h2'])

<div {{ $attributes->class(['flex flex-col gap-3', 'mx-auto max-w-[45rem] items-center text-center' => $align === 'center', 'min-w-0 flex-1 items-start text-start' => $align !== 'center']) }}>
    @if ($eyebrow)
        <p class="type-overline text-fg-brand">{{ $eyebrow }}</p>
    @endif
    <{{ $headingTag }} @if ($id) id="{{ $id }}" @endif class="type-h2 text-fg">{{ $heading }}</{{ $headingTag }}>
    @if ($subheading)
        <p class="type-body-lg text-fg-secondary">{{ $subheading }}</p>
    @endif
</div>
