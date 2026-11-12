@props(['title'])

{{-- Minimal public checkout shell (P03): logo, language switch and the secure checkout marker. Never indexed. --}}
<x-layouts.base :title="$title">
    <x-slot:meta>
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">
    </x-slot:meta>
    <div class="min-h-screen bg-[radial-gradient(ellipse_at_bottom,var(--color-brand-subtle),transparent_60%)]">
        <header class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-6 sm:px-8">
            <x-ui.logo />
            <div class="flex items-center gap-3">
                <span class="hidden items-center gap-1.5 text-sm text-fg-secondary sm:inline-flex">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-fg-success" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    {{ __('Secure checkout') }}
                </span>
                <x-language-switch />
            </div>
        </header>
        <main id="main" class="mx-auto max-w-5xl px-4 pb-16 sm:px-8">
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
