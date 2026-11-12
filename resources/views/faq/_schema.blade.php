{{-- FAQPage structured data. JSON_HEX_* flags escape <, >, &, ' and " so content can never close the script tag. --}}
@php
    $schemaLocale = app()->getLocale();
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'inLanguage' => $schemaLocale,
        'mainEntity' => $faqs->map(fn ($faq) => $faq->exactTranslation($schemaLocale))->filter()->map(fn ($translation) => [
            '@type' => 'Question',
            'name' => $translation->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $translation->answerText()],
        ])->values()->all(),
    ];
@endphp
@if ($faqSchema['mainEntity'] !== [])
    <script type="application/ld+json">@json($faqSchema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
@endif
