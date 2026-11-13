{{--
    Figma "Accordion Item" (native <details>). Pass a FaqTranslation (`translation`), or a plain `question` with the answer as the slot
    (service FAQs store plain-text answers).
--}}
@props(['translation' => null, 'question' => null, 'open' => false])

<details data-faq-item @if ($open) open @endif class="group rounded-lg border border-line bg-surface transition-colors open:border-line-brand hover:border-line-strong open:hover:border-line-brand">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-lg px-6 py-5 type-label-lg text-fg [&::-webkit-details-marker]:hidden">
        <span>{{ $translation?->question ?? $question }}</span>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-secondary transition-transform group-open:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    @if ($translation)
        <div class="-mt-2 flex flex-col gap-3 px-6 pb-5 type-body-md text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_ol]:list-decimal [&_ol]:ps-6 [&_strong]:text-fg [&_ul]:list-disc [&_ul]:ps-6">{!! $translation->answerHtml() !!}</div>
    @elseif ($slot->isNotEmpty())
        <div class="-mt-2 px-6 pb-5 type-body-md text-fg-secondary">{{ $slot }}</div>
    @endif
</details>
