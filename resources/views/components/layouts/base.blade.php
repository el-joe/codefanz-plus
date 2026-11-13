@props(['title' => null, 'entry' => null, 'themeable' => false])

@php
    $locale = app()->getLocale();
    $direction = \App\Models\Language::active()[$locale]['direction'] ?? 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}CodeFanz</title>
    @isset($meta) {{ $meta }} @endisset
    @if ($themeable)
        {{-- Light is the designed default; dark only when the user chose it with the theme toggle (admin, portal). --}}
        <meta name="codefanz-themeable" content="1">
        <script>
            try { if (localStorage.getItem('codefanz-theme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) {}
        </script>
    @endif
    @fonts
    @vite(['resources/css/app.css', $entry ?? 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-fg">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2">{{ __('Skip to content') }}</a>

    {{ $slot }}

    <div data-toast-region aria-live="polite" class="pointer-events-none fixed bottom-4 end-4 z-50 flex w-full max-w-sm flex-col gap-2"></div>
    @if (session('toast'))
        <span hidden data-flash="{{ session('toast') }}"></span>
    @endif

    <dialog data-confirm-dialog class="m-auto w-full max-w-md rounded-2xl bg-surface-raised p-6 text-fg shadow-xl backdrop:bg-ink-950/50">
        <p data-confirm-message class="text-base font-semibold"></p>
        <div class="mt-6 flex justify-end gap-3">
            <form method="dialog"><x-ui.button variant="secondary">{{ __('Cancel') }}</x-ui.button></form>
            <x-ui.button variant="danger" type="button" data-confirm-accept data-default-label="{{ __('Confirm') }}">{{ __('Confirm') }}</x-ui.button>
        </div>
    </dialog>
</body>
</html>
