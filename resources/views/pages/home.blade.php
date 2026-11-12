<x-layouts.base :title="__('Turning Ideas Into Powerful Digital Products')">
    <x-slot:meta>
        <meta name="description" content="{{ __('CodeFanz designs, builds and scales web platforms, mobile apps, SaaS products and AI-powered automation.') }}">
        @foreach (\App\Models\Language::active() as $code => $language)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ route('home', ['locale' => $code]) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ route('home', ['locale' => \App\Models\Language::defaultCode()]) }}">
        <link rel="canonical" href="{{ route('home') }}">
    </x-slot:meta>

    <header class="border-b border-line-subtle bg-surface">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-5 py-4 lg:px-20">
            <a href="{{ route('home') }}" class="flex-1" aria-label="{{ __('CodeFanz home') }}"><x-ui.logo /></a>
            <x-language-switch />
            <x-ui.button :href="route('login')" variant="ghost">{{ __('Client Portal') }}</x-ui.button>
        </div>
    </header>

    <main id="main">
        <section class="bg-[radial-gradient(ellipse_at_top_right,rgb(110_86_255/0.18),transparent_60%)]">
            <div class="mx-auto flex max-w-7xl flex-col gap-7 px-5 py-20 lg:px-20 lg:py-28">
                <x-ui.badge tone="accent" class="w-fit">{{ __('Software development & digital solutions') }}</x-ui.badge>
                <h1 class="max-w-4xl text-4xl font-extrabold leading-tight tracking-tight text-fg lg:text-7xl">
                    {{ __('Turning Ideas Into') }} <span class="text-fg-brand">{{ __('Powerful Digital Products') }}</span>
                </h1>
                <p class="max-w-2xl text-lg text-fg-secondary">{{ __('We design, build and scale web platforms, mobile apps, SaaS products and AI-powered automation — engineered for reliability and growth.') }}</p>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button href="mailto:hello@codefanz.com" size="lg">{{ __('Start Your Project') }}</x-ui.button>
                </div>
            </div>
        </section>
    </main>

    <footer class="dark bg-canvas text-fg">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-8 lg:px-20">
            <x-ui.logo />
            <p class="text-sm text-fg-tertiary">© {{ now()->year }} {{ __('CodeFanz. All rights reserved.') }}</p>
        </div>
    </footer>
</x-layouts.base>
