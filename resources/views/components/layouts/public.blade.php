@props(['title', 'description' => null, 'alternates' => [], 'robots' => null])

@php
    $menus = app(\App\Services\MenuResolver::class);
    $locale = app()->getLocale();
    $settings = app(\App\Services\Settings::class);
    $header = $menus->resolveOrFallback(\App\Enums\MenuLocation::Header);
    $footerColumns = collect([\App\Enums\MenuLocation::FooterServices, \App\Enums\MenuLocation::FooterCompany, \App\Enums\MenuLocation::FooterResources])
        ->mapWithKeys(fn ($location) => [$location->value => ['heading' => $location->heading(), 'items' => $menus->resolveOrFallback($location)]])
        ->filter(fn ($column) => $column['items'] !== []);
    $legal = $menus->resolveOrFallback(\App\Enums\MenuLocation::FooterLegal);
    $email = $settings->get('contact', 'email') ?: 'hello@codefanz.com';
    $phone = $settings->get('contact', 'phone');
    $whatsapp = $settings->get('contact', 'whatsapp');
    $address = ($settings->get('contact', 'address') ?? [])[$locale] ?? null;
    $tagline = ($settings->get('general', 'tagline') ?? [])[$locale] ?? __('Turning ideas into powerful digital products — web, mobile, SaaS and AI solutions for growing businesses.');
    $current = url()->current();
    $isCurrent = fn (string $url): bool => rtrim($url, '/') === rtrim($current, '/');
    $newTab = fn (array $link): string => $link['new_tab'] ? 'target="_blank" rel="noopener noreferrer"' : '';
@endphp

<x-layouts.base :title="$title">
    <x-slot:meta>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @if ($robots)
            <meta name="robots" content="{{ $robots }}">
        @endif
        @foreach ($alternates as $code => $url)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}">
        @endforeach
        @isset($alternates[\App\Models\Language::defaultCode()])
            <link rel="alternate" hreflang="x-default" href="{{ $alternates[\App\Models\Language::defaultCode()] }}">
        @endisset
        <link rel="canonical" href="{{ url()->current() }}">
    </x-slot:meta>

    <header class="relative z-30 border-b border-line-subtle bg-surface">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-5 py-4 lg:px-20">
            <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ __('CodeFanz home') }}"><x-ui.logo /></a>

            <nav class="hidden flex-1 justify-center xl:flex" aria-label="{{ __('Main navigation') }}" data-main-nav>
                <ul class="flex items-center gap-1">
                    @foreach ($header as $link)
                        <li @class(['relative' => $link['children'] !== []]) @if ($link['children'] !== []) data-dropdown @endif>
                            <span class="flex items-center">
                                <a href="{{ $link['url'] }}" {!! $newTab($link) !!} @if ($isCurrent($link['url'])) aria-current="page" @endif
                                   class="rounded-md px-3 py-2 text-sm font-medium text-fg-secondary hover:bg-subtle hover:text-fg aria-[current=page]:text-fg-brand">{{ $link['label'] }}</a>
                                @if ($link['children'] !== [])
                                    <button type="button" data-dropdown-trigger class="-ms-2 rounded-md p-1.5 text-fg-secondary hover:bg-subtle" aria-label="{{ __('Show :item links', ['item' => $link['label']]) }}">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                @endif
                            </span>
                            @if ($link['children'] !== [])
                                <ul data-dropdown-menu hidden class="absolute start-0 top-full z-40 mt-1 w-60 rounded-lg border border-line bg-surface-raised p-1.5 shadow-lg">
                                    @foreach ($link['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" {!! $newTab($child) !!} class="block rounded-md px-3 py-2 text-sm text-fg hover:bg-subtle">{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="ms-auto flex items-center gap-2 xl:ms-0">
                <x-language-switch :alternates="$alternates" />
                <x-ui.button :href="route('login')" variant="ghost" class="whitespace-nowrap max-sm:hidden">{{ __('Client Portal') }}</x-ui.button>
                <x-ui.button :href="'mailto:'.$email" class="whitespace-nowrap max-md:hidden">
                    {{ __('Start Your Project') }}
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </x-ui.button>
                <button type="button" hidden data-nav-toggle aria-controls="mobile-nav" aria-expanded="false"
                        class="rounded-md border border-line p-2 text-fg hover:bg-subtle xl:hidden">
                    <span class="sr-only" data-nav-label-open>{{ __('Open menu') }}</span>
                    <span class="sr-only" data-nav-label-close hidden>{{ __('Close menu') }}</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile navigation: visible without JavaScript; the nav module turns it into a disclosure. --}}
        <nav id="mobile-nav" data-nav-panel class="border-t border-line-subtle px-5 py-3 xl:hidden" aria-label="{{ __('Mobile navigation') }}">
            <ul class="flex flex-col gap-0.5">
                @foreach ($header as $link)
                    <li>
                        <a href="{{ $link['url'] }}" {!! $newTab($link) !!} @if ($isCurrent($link['url'])) aria-current="page" @endif class="block rounded-md px-3 py-2.5 text-base font-semibold text-fg hover:bg-subtle aria-[current=page]:text-fg-brand">{{ $link['label'] }}</a>
                        @if ($link['children'] !== [])
                            <ul class="ms-4 flex flex-col border-s border-line-subtle ps-2">
                                @foreach ($link['children'] as $child)
                                    <li><a href="{{ $child['url'] }}" {!! $newTab($child) !!} class="block rounded-md px-3 py-2 text-sm text-fg-secondary hover:bg-subtle">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
                <li class="mt-2 flex flex-wrap gap-2 border-t border-line-subtle pt-3">
                    <x-ui.button :href="'mailto:'.$email" size="sm">{{ __('Start Your Project') }}</x-ui.button>
                    <x-ui.button :href="route('login')" variant="secondary" size="sm">{{ __('Client Portal') }}</x-ui.button>
                </li>
            </ul>
        </nav>
    </header>

    <main id="main">{{ $slot }}</main>

    <footer class="dark bg-canvas text-fg">
        <div class="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-14 lg:px-20">
            <div class="grid gap-10 lg:grid-cols-[1.2fr_2fr]">
                <div class="flex flex-col gap-4">
                    <x-ui.logo />
                    <p class="max-w-xs text-sm text-fg-secondary">{{ $tagline }}</p>
                    <ul class="flex gap-2" aria-label="{{ __('Contact channels') }}">
                        @if ($whatsapp)
                            <li><a href="https://wa.me/{{ ltrim($whatsapp, '+') }}" target="_blank" rel="noopener noreferrer" class="flex size-10 items-center justify-center rounded-lg bg-surface text-fg hover:bg-subtle" aria-label="{{ __('WhatsApp') }}">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M3 21l1.65-3.8A9 9 0 1 1 7.8 20.4z"/></svg>
                            </a></li>
                        @endif
                        <li><a href="mailto:{{ $email }}" class="flex size-10 items-center justify-center rounded-lg bg-surface text-fg hover:bg-subtle" aria-label="{{ __('Email') }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </a></li>
                        @if ($phone)
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="flex size-10 items-center justify-center rounded-lg bg-surface text-fg hover:bg-subtle" aria-label="{{ __('Phone') }}">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>
                            </a></li>
                        @endif
                    </ul>
                </div>
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                    @foreach ($footerColumns as $key => $column)
                        <nav aria-labelledby="footer-{{ $key }}" class="flex flex-col gap-3">
                            <h2 id="footer-{{ $key }}" class="text-sm font-semibold text-fg">{{ $column['heading'] }}</h2>
                            <ul class="flex flex-col gap-2.5 text-sm">
                                @foreach ($column['items'] as $link)
                                    <li><a href="{{ $link['url'] }}" {!! $newTab($link) !!} class="text-fg-secondary hover:text-fg">{{ $link['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    @endforeach
                    <div class="flex flex-col gap-3">
                        <h2 class="text-sm font-semibold text-fg">{{ __('Get in touch') }}</h2>
                        <ul class="flex flex-col gap-2.5 text-sm text-fg-secondary">
                            <li><a href="mailto:{{ $email }}" class="hover:text-fg" dir="ltr">{{ $email }}</a></li>
                            @if ($phone)
                                <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-fg" dir="ltr">{{ $phone }}</a></li>
                            @endif
                            @if ($address)
                                <li>{{ $address }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-4 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-fg-tertiary">© {{ now()->year }} {{ __('CodeFanz. All rights reserved.') }}</p>
                @if ($legal !== [])
                    <nav aria-label="{{ __('Legal') }}">
                        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                            @foreach ($legal as $link)
                                <li><a href="{{ $link['url'] }}" {!! $newTab($link) !!} class="text-fg-secondary hover:text-fg">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
    </footer>
</x-layouts.base>
