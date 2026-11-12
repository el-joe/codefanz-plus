{{-- One accordion question. Expects a FaqTranslation. --}}
@props(['translation', 'open' => false])

<details data-faq-item @if ($open) open @endif class="group rounded-xl border border-line bg-surface open:border-line-brand">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-base font-semibold text-fg [&::-webkit-details-marker]:hidden">
        <span>{{ $translation->question }}</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="shrink-0 text-fg-tertiary transition-transform group-open:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <div class="flex flex-col gap-3 px-5 pb-5 text-sm leading-relaxed text-fg-secondary [&_a]:text-fg-brand [&_a]:underline [&_ol]:list-decimal [&_ol]:ps-6 [&_strong]:text-fg [&_ul]:list-disc [&_ul]:ps-6">{!! $translation->answerHtml() !!}</div>
</details>
