{{-- About page (Admin → Page sections → About page). Each block: section + copy (translated or default). --}}
<x-layouts.public
    :title="$intro['heading']"
    :description="$intro['subheading']"
    :alternates="\App\Models\Language::active()->keys()->mapWithKeys(fn ($code) => [$code => route('about', ['locale' => $code])])->all()">
    <x-site.page-hero :eyebrow="$intro['eyebrow']" :title="$intro['heading']" :lead="$intro['subheading']" :crumbs="[__('About Us') => null]" />

    @foreach ($blocks as $block)
        @include('about.sections.'.$block['section']->type->value, [
            'copy' => $block['copy'],
            'headingId' => 'section-'.$block['section']->type->value,
            'statistics' => $statistics,
            // Bands alternate surface / canvas by position, like the Figma screen.
            'tone' => $loop->odd ? 'surface' : 'canvas',
        ])
    @endforeach

    <x-site.cta-band :tone="$blocks->count() % 2 === 0 ? 'surface' : 'canvas'" />
</x-layouts.public>
