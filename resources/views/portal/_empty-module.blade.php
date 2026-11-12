{{-- Honest empty state for a portal module (no sample data). Expects $title, $description, $icon (SVG inner markup). --}}
<div class="flex flex-col items-center gap-2 px-4 py-8 text-center" data-empty-state>
    <span class="flex size-12 items-center justify-center rounded-full bg-brand-subtle text-fg-brand" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
    </span>
    <p class="text-base font-semibold text-fg">{{ $title }}</p>
    <p class="max-w-sm text-sm text-fg-secondary">{{ $description }}</p>
</div>
