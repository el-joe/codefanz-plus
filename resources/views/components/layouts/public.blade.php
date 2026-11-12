@props(['title', 'description' => null, 'alternates' => []])

<x-layouts.base :title="$title">
    <x-slot:meta>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @foreach ($alternates as $code => $url)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}">
        @endforeach
        @isset($alternates[\App\Models\Language::defaultCode()])
            <link rel="alternate" hreflang="x-default" href="{{ $alternates[\App\Models\Language::defaultCode()] }}">
        @endisset
        <link rel="canonical" href="{{ url()->current() }}">
    </x-slot:meta>

    <header class="border-b border-line-subtle bg-surface">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-5 py-4 lg:px-20">
            <a href="{{ route('home') }}" class="flex-1" aria-label="{{ __('CodeFanz home') }}"><x-ui.logo /></a>
            <x-language-switch :alternates="$alternates" />
            <x-ui.button :href="route('login')" variant="ghost">{{ __('Client Portal') }}</x-ui.button>
        </div>
    </header>

    <main id="main">{{ $slot }}</main>

    <footer class="dark bg-canvas text-fg">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-8 lg:px-20">
            <x-ui.logo />
            <p class="text-sm text-fg-tertiary">© {{ now()->year }} {{ __('CodeFanz. All rights reserved.') }}</p>
        </div>
    </footer>
</x-layouts.base>
