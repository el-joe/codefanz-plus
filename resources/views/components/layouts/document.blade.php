@props(['title', 'backUrl'])

{{-- Printable billing document (invoice, receipt): toolbar on screen, A4 page in print. Never indexed. --}}
<x-layouts.base :title="$title">
    <x-slot:meta>
        <meta name="robots" content="noindex, nofollow">
        <style>@page { size: A4; margin: 14mm; }</style>
    </x-slot:meta>
    <div class="min-h-screen bg-subtle py-6 print:bg-white print:py-0">
        <div class="mx-auto mb-4 flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 print:hidden">
            <a href="{{ $backUrl }}" class="text-sm font-semibold text-fg-brand hover:underline"><span aria-hidden="true" class="inline-block rtl:rotate-180">←</span> {{ __('Back') }}</a>
            <div class="flex items-center gap-2">
                @foreach (\App\Models\Language::active() as $code => $language)
                    @continue($code === app()->getLocale())
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" class="rounded-md px-2.5 py-1.5 text-sm font-semibold text-fg hover:bg-surface">{{ $language['native_name'] }}</a>
                @endforeach
                <x-ui.button size="sm" type="button" data-print>{{ __('Print / Save as PDF') }}</x-ui.button>
            </div>
        </div>
        <main id="main" class="mx-auto max-w-3xl bg-white p-8 text-ink-950 shadow-sm sm:p-12 print:max-w-none print:p-0 print:shadow-none" data-billing-document>
            {{ $slot }}
        </main>
    </div>
</x-layouts.base>
