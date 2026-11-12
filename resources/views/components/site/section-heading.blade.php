{{-- Section header: eyebrow, heading and optional subheading. --}}
@props(['eyebrow' => null, 'heading', 'subheading' => null, 'id' => null, 'align' => 'center'])

<div @class(['flex flex-col gap-3', 'items-center text-center' => $align === 'center'])>
    @if ($eyebrow)
        <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ $eyebrow }}</p>
    @endif
    <h2 @if ($id) id="{{ $id }}" @endif class="max-w-3xl text-3xl font-bold tracking-tight text-fg lg:text-4xl">{{ $heading }}</h2>
    @if ($subheading)
        <p class="max-w-2xl text-lg text-fg-secondary">{{ $subheading }}</p>
    @endif
</div>
