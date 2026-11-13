{{-- Figma "Open positions" row: title, department / location / type pills and an "Apply" button; the whole row links to the position. --}}
@php($cardTranslation = $career->exactTranslation(app()->getLocale()))
<li class="group relative flex flex-wrap items-center gap-4 rounded-xl border border-line bg-surface px-5 py-4 shadow-xs transition hover:border-line-brand hover:shadow-md sm:px-6">
    <div class="flex min-w-0 flex-1 flex-col gap-2">
        <h3 class="type-label-lg text-fg">
            <a href="{{ route('careers.show', ['slug' => $cardTranslation->slug]) }}" class="after:absolute after:inset-0 after:rounded-xl group-hover:text-fg-brand focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-line-focus">{{ $cardTranslation->title }}</a>
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
            <p class="type-body-sm text-fg-secondary">{{ $cardTranslation->summary }}</p>
        @endif
    </div>
    <span class="inline-flex items-center gap-2 rounded-md border border-line-strong bg-surface px-3 py-1.5 type-label-md text-fg transition-colors group-hover:border-line-brand group-hover:text-fg-brand" aria-hidden="true">
        {{ __('Apply now') }}
        <x-ui.arrow :size="16" />
    </span>
</li>
