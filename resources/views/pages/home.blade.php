{{-- Homepage built from the managed sections (Admin → Homepage). Each block: section, copy (translated or default) and data. --}}
@php
    $contactHref = route('quote');
    $hero = $blocks->first(fn ($block) => $block['section']->type === \App\Enums\PageSectionType::Hero);
@endphp

<x-layouts.public
    :title="$hero['copy']['heading'] ?? __('Turning Ideas Into Powerful Digital Products')"
    :description="$hero['copy']['subheading'] ?? __('CodeFanz designs, builds and scales web platforms, mobile apps, SaaS products and AI-powered automation.')"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('home', ['locale' => $code])])->all()">
    @foreach ($blocks as $block)
        @include('home.sections.'.$block['section']->type->value, [
            'section' => $block['section'],
            'copy' => $block['copy'],
            'data' => $block['data'],
            'contactHref' => $contactHref,
            'headingId' => 'section-'.$block['section']->type->value,
        ])
    @endforeach
</x-layouts.public>
