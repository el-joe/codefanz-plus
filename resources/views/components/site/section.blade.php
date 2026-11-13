{{--
    Public page band (Figma section rhythm): full-width background, 1280px content (80px gutters, 20px on mobile),
    96px vertical padding (56px on mobile). tone: canvas | surface | subtle | none. Extra attributes land on <section>.
--}}
@props(['tone' => 'surface', 'inner' => 'flex flex-col gap-12'])

@php
    $tones = ['canvas' => 'bg-canvas', 'surface' => 'bg-surface', 'subtle' => 'bg-subtle', 'none' => ''];
@endphp

<section {{ $attributes->merge(['class' => 'section-y '.($tones[$tone] ?? $tones['surface'])]) }}>
    <div class="container-page {{ $inner }}">
        {{ $slot }}
    </div>
</section>
