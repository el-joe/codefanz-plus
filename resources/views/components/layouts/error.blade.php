{{--
    Error page shell with the public look. It must render without the database or a session (500, 503):
    languages come from config, no Settings/menus/Language queries, no CSRF token.
    Each error view calls ErrorPageLocale::apply() first, so its translated strings use the right language.
--}}
@props(['code', 'title', 'message' => null, 'tone' => 'brand', 'footer' => true, 'image' => null])

@php
    $languages = collect(config('codefanz.languages'))->keyBy('code');
    $locale = app()->getLocale();
    $direction = $languages[$locale]['direction'] ?? 'ltr';
    $home = route('home', ['locale' => $locale]);
    $gradient = $tone === 'danger'
        ? 'bg-[linear-gradient(90deg,#dc2626,#f79009)]'
        : 'bg-[linear-gradient(90deg,#5a3df5,#6e56ff_55%,#1ad5c8)]';
    $glow = $tone === 'danger' ? 'rgb(240_68_56/0.10)' : 'rgb(110_86_255/0.12)';
    // Static navigation (Figma navbar order): route() only, never the menu tables.
    $nav = [
        __('Services') => route('services.index', ['locale' => $locale]),
        __('Portfolio') => route('portfolio.index', ['locale' => $locale]),
        __('Products') => route('products.index', ['locale' => $locale]),
        __('Technologies') => route('technologies.index', ['locale' => $locale]),
        __('Blog') => route('blog.index', ['locale' => $locale]),
        __('About') => route('about', ['locale' => $locale]),
    ];
    $footerColumns = [
        __('Company') => [__('About Us') => route('about', ['locale' => $locale]), __('Portfolio') => route('portfolio.index', ['locale' => $locale]), __('Careers') => route('careers.index', ['locale' => $locale]), __('Contact') => route('contact', ['locale' => $locale])],
        __('Resources') => [__('Blog') => route('blog.index', ['locale' => $locale]), __('Products') => route('products.index', ['locale' => $locale]), __('Technologies') => route('technologies.index', ['locale' => $locale]), __('FAQ') => route('faq.index', ['locale' => $locale])],
    ];
    $shortNames = ['ar' => 'عربي'];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · CodeFanz</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-v2-canvas text-fg" data-site>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2">{{ __('Skip to content') }}</a>

    {{-- v2 glass header in its static (scrolled) state: error pages load no JavaScript. --}}
    <header data-site-header class="relative z-10">
        <div class="container-page flex items-center gap-4 py-3 lg:py-4">
            <a href="{{ $home }}" class="shrink-0 rounded-md" aria-label="{{ __('CodeFanz home') }}"><x-ui.logo :size="34" class="lg:hidden" /><x-ui.logo :size="40" class="max-lg:hidden" /></a>
            <nav class="hidden flex-1 justify-center xl:flex" aria-label="{{ __('Main navigation') }}">
                <ul class="flex items-center gap-x-7">
                    @foreach ($nav as $label => $url)
                        <li><a href="{{ $url }}" class="rounded-sm py-2 type-label-md whitespace-nowrap text-fg-secondary transition-colors hover:text-fg">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="ms-auto flex items-center gap-2 lg:gap-3 xl:ms-0">
                @foreach ($languages as $languageCode => $language)
                    @continue($languageCode === $locale)
                    <a href="{{ route('home', ['locale' => $languageCode]) }}" hreflang="{{ $languageCode }}" lang="{{ $languageCode }}" class="inline-flex min-h-10 min-w-10 items-center justify-center gap-1.5 rounded-full px-3.5 py-2 type-label-md text-fg hover:bg-v2-tint">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-primary"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                        <span class="max-sm:sr-only">{{ $shortNames[$languageCode] ?? $language['native_name'] }}</span>
                    </a>
                @endforeach
                <x-ui.button :href="route('login')" variant="ghost" class="whitespace-nowrap max-lg:hidden">{{ __('Client Portal') }}</x-ui.button>
                <x-ui.button :href="route('quote', ['locale' => $locale])" class="whitespace-nowrap max-md:hidden" arrow>{{ __('Start Your Project') }}</x-ui.button>
            </div>
        </div>
    </header>

    <main id="main" class="flex flex-1 items-center bg-[radial-gradient(ellipse_60%_55%_at_center,{{ $glow }},transparent)]">
        <div class="mx-auto flex max-w-2xl flex-col items-center gap-5 px-5 py-16 text-center lg:py-24">
            @if ($image)
                {{-- Design v2 404: the generated artwork above a smaller gradient code (72/76). --}}
                <div class="size-70 overflow-hidden rounded-3xl border border-v2-hairline bg-v2-tint shadow-v2-card sm:size-90 sm:rounded-4xl">
                    <x-site.image :name="$image" sizes="(min-width: 640px) 360px, 280px" eager described class="size-full object-cover" />
                </div>
                <p class="bg-clip-text text-[4.5rem] leading-[4.75rem] font-bold tracking-[-0.035em] text-transparent {{ $gradient }}" aria-hidden="true" dir="ltr">{{ $code }}</p>
            @else
                <p class="bg-clip-text pb-2 text-[6rem] leading-none font-extrabold tracking-[-0.25rem] text-transparent sm:text-[10rem] {{ $gradient }}" aria-hidden="true" dir="ltr">{{ $code }}</p>
            @endif
            <h1 class="type-v2-h2-mobile text-balance text-v2-display sm:type-v2-h2">{{ $title }}</h1>
            @if ($message)
                <p class="max-w-lg type-body-lg text-fg-secondary">{{ $message }}</p>
            @endif
            {{ $slot }}
        </div>
    </main>

    @if ($footer)
        <footer class="dark bg-v2-night text-fg">
            <div class="container-page flex flex-col gap-12 pt-14 pb-8 lg:pt-16">
                <div class="flex flex-col gap-10 lg:flex-row lg:justify-between lg:gap-16">
                    <div class="flex flex-col gap-5 lg:w-[21.25rem] lg:shrink-0">
                        <x-ui.logo :size="40" class="self-start" />
                        <p class="type-body-sm text-fg-secondary">{{ __('Turning ideas into powerful digital products — web, mobile, SaaS and AI solutions for growing businesses.') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-10 sm:grid-cols-3 lg:flex lg:gap-16">
                        @foreach ($footerColumns as $heading => $links)
                            <nav aria-labelledby="error-footer-{{ $loop->index }}" class="flex flex-col gap-3">
                                <h2 id="error-footer-{{ $loop->index }}" class="type-label-md text-fg">{{ $heading }}</h2>
                                <ul class="flex flex-col gap-3 type-body-sm">
                                    @foreach ($links as $label => $url)
                                        <li><a href="{{ $url }}" class="text-fg-secondary transition-colors hover:text-fg">{{ $label }}</a></li>
                                    @endforeach
                                </ul>
                            </nav>
                        @endforeach
                    </div>
                </div>
                <div class="border-t border-v2-hairline-dark pt-6">
                    <p class="type-body-sm text-fg-tertiary">© {{ now()->year }} {{ __('CodeFanz. All rights reserved.') }}</p>
                </div>
            </div>
        </footer>
    @endif
</body>
</html>
