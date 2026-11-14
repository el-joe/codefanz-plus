{{--
    Public page band (Figma section rhythm): full-width background, 1280px content (80px gutters, 20px on mobile),
    128px vertical padding (56px on mobile). tone: canvas | surface | subtle | none. Extra attributes land on <section>.
    Design v2: white bands use the near-white v2 canvas and grey bands the lavender tint.
--}}
@props(['tone' => 'surface', 'inner' => 'flex flex-col gap-12'])

@php
    $tones = ['canvas' => 'bg-v2-canvas', 'surface' => 'bg-v2-canvas', 'subtle' => 'bg-v2-tint', 'none' => ''];
@endphp

<section {{ $attributes->merge(['class' => 'section-y '.($tones[$tone] ?? $tones['surface'])]) }}>
    <div class="container-page {{ $inner }}">
        {{ $slot }}
    </div>
</section>
