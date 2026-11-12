@php($cardTranslation = $career->exactTranslation(app()->getLocale()))
<li class="relative flex flex-wrap items-center gap-4 rounded-xl border border-line bg-surface p-5 hover:border-line-brand">
    <div class="flex min-w-0 flex-1 flex-col gap-2">
        <h3 class="text-lg font-semibold text-fg">
            <a href="{{ route('careers.show', ['slug' => $cardTranslation->slug]) }}" class="after:absolute after:inset-0">{{ $cardTranslation->title }}</a>
        </h3>
        <p class="flex flex-wrap gap-1.5">
            @if ($cardTranslation->department)
                <x-ui.badge tone="brand" :dot="false">{{ $cardTranslation->department }}</x-ui.badge>
            @endif
            @if ($cardTranslation->location || $career->is_remote)
                <x-ui.badge :dot="false">{{ collect([$cardTranslation->location, $career->is_remote ? __('Remote') : null])->filter()->implode(' · ') }}</x-ui.badge>
            @endif
            <x-ui.badge :dot="false">{{ $career->employment_type->label() }}</x-ui.badge>
        </p>
        @if ($cardTranslation->summary)
            <p class="text-sm text-fg-secondary">{{ $cardTranslation->summary }}</p>
        @endif
    </div>
    <span class="inline-flex items-center gap-2 rounded-md border border-line-strong px-4 py-2 text-sm font-semibold text-fg" aria-hidden="true">
        {{ __('View & apply') }}
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
    </span>
</li>
