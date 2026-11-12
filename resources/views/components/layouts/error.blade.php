{{--
    Error page shell with the public look. It must render without the database or a session (500, 503):
    languages come from config, no Settings/menus/Language queries, no CSRF token.
    Each error view calls ErrorPageLocale::apply() first, so its translated strings use the right language.
--}}
@props(['code', 'title', 'message' => null, 'tone' => 'brand'])

@php
    $languages = collect(config('codefanz.languages'))->keyBy('code');
    $locale = app()->getLocale();
    $direction = $languages[$locale]['direction'] ?? 'ltr';
    $home = route('home', ['locale' => $locale]);
    $gradient = $tone === 'danger'
        ? 'bg-[linear-gradient(90deg,#dc2626,#f59e0b)]'
        : 'bg-[linear-gradient(90deg,var(--color-brand-600),var(--color-accent-500))]';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · CodeFanz</title>
    <script>
        try { if ((localStorage.getItem('codefanz-theme') ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')) === 'dark') document.documentElement.classList.add('dark'); } catch (e) {}
    </script>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-canvas text-fg">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2">{{ __('Skip to content') }}</a>

    <header class="border-b border-line-subtle bg-surface">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-5 py-4 lg:px-20">
            <a href="{{ $home }}" class="shrink-0" aria-label="{{ __('CodeFanz home') }}"><x-ui.logo /></a>
            <nav class="ms-auto flex items-center gap-1" aria-label="{{ __('Main navigation') }}">
                <a href="{{ route('services.index', ['locale' => $locale]) }}" class="rounded-md px-3 py-2 text-sm font-medium text-fg-secondary hover:bg-subtle hover:text-fg max-sm:hidden">{{ __('Services') }}</a>
                <a href="{{ route('portfolio.index', ['locale' => $locale]) }}" class="rounded-md px-3 py-2 text-sm font-medium text-fg-secondary hover:bg-subtle hover:text-fg max-sm:hidden">{{ __('Portfolio') }}</a>
                @foreach ($languages as $languageCode => $language)
                    @continue($languageCode === $locale)
                    <a href="{{ route('home', ['locale' => $languageCode]) }}" hreflang="{{ $languageCode }}" lang="{{ $languageCode }}" class="rounded-md px-2.5 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">{{ $language['native_name'] }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="main" class="flex flex-1 items-center bg-[radial-gradient(ellipse_at_center,rgb(110_86_255/0.10),transparent_65%)]">
        <div class="mx-auto flex max-w-2xl flex-col items-center gap-5 px-5 py-20 text-center">
            <p class="bg-clip-text text-8xl font-extrabold leading-none tracking-tight text-transparent sm:text-9xl {{ $gradient }}" aria-hidden="true" dir="ltr">{{ $code }}</p>
            <h1 class="text-3xl font-bold tracking-tight text-fg sm:text-4xl">{{ $title }}</h1>
            @if ($message)
                <p class="max-w-lg text-lg text-fg-secondary">{{ $message }}</p>
            @endif
            {{ $slot }}
        </div>
    </main>

    <footer class="dark bg-canvas text-fg">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 sm:flex-row sm:items-center sm:justify-between lg:px-20">
            <x-ui.logo />
            <p class="text-sm text-fg-tertiary">© {{ now()->year }} {{ __('CodeFanz. All rights reserved.') }}</p>
        </div>
    </footer>
</body>
</html>
