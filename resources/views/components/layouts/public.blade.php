@props(['title', 'description' => null, 'alternates' => [], 'robots' => null, 'image' => null, 'ogType' => 'website', 'canonical' => null, 'seoable' => null])

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
    $quoteUrl = route('quote');
    // OpenGraph: locale as language_TERRITORY; the image is the cover's ~1200px WebP variant when there is one.
    $ogLocale = fn (string $code): string => ['en' => 'en_US', 'ar' => 'ar_AR'][$code] ?? $code;
    $ogImage = $image instanceof \App\Models\Media
        ? ($image->variantsFor('webp')->first(fn ($variant) => $variant->width >= 1200) ?? $image->largestVariant('webp'))
        : null;
    // SEO editor overrides (Admin → SEO): content items pass :seoable; fixed site pages are matched by route name.
    $seoResolver = app(\App\Services\Seo\SeoMetaResolver::class);
    $routeName = request()->route()?->getName();
    $seoMeta = $seoable instanceof \Illuminate\Database\Eloquent\Model
        ? $seoResolver->forModel($seoable, $locale)
        : ($routeName && app(\App\Services\Seo\SeoTargets::class)->isStaticRoute($routeName) && request()->query() === [] ? $seoResolver->forRoute($routeName, $locale) : null);
    if ($seoMeta) {
        $title = $seoMeta['title'] ?: $title;
        $description = $seoMeta['description'] ?: $description;
        $canonical = $canonical ?: $seoMeta['canonical_url'];
        $robots = $seoMeta['noindex'] ? 'noindex, follow' : $robots;
    }
    $structuredData = app(\App\Services\Seo\StructuredData::class);
    $consent = app(\App\Services\Analytics\AnalyticsConsent::class);
    $gaMeasurementId = $consent->shouldLoadGa4(request()) ? $consent->measurementId() : null;
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
        <link rel="canonical" href="{{ $canonical ?: url()->current() }}">
        <meta property="og:site_name" content="CodeFanz">
        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:title" content="{{ $title }}">
        @if ($description)
            <meta property="og:description" content="{{ $description }}">
        @endif
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:locale" content="{{ $ogLocale($locale) }}">
        @foreach (array_keys($alternates) as $code)
            @continue($code === $locale)
            <meta property="og:locale:alternate" content="{{ $ogLocale($code) }}">
        @endforeach
        @if ($ogImage)
            <meta property="og:image" content="{{ url($ogImage->url()) }}">
            <meta property="og:image:width" content="{{ $ogImage->width }}">
            <meta property="og:image:height" content="{{ $ogImage->height }}">
            <meta property="og:image:alt" content="{{ $image->altText() }}">
        @endif
        <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
        {{ $structuredData->script($structuredData->organization()) }}
        {{ $structuredData->script($structuredData->website()) }}
        @if ($gaMeasurementId)
            {{-- GA4 only after the visitor opted in; consent mode defaults to denied, then grants analytics only. --}}
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaMeasurementId }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'});gtag('consent','update',{analytics_storage:'granted'});gtag('js',new Date());gtag('config',@json($gaMeasurementId),{anonymize_ip:true});</script>
        @endif
        {{ $head ?? '' }}
    </x-slot:meta>

    <header class="sticky top-0 z-30 border-b border-line-subtle bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/90">
        <div class="container-page flex items-center gap-4 py-3 lg:py-4">
            <a href="{{ route('home') }}" class="shrink-0 rounded-md" aria-label="{{ __('CodeFanz home') }}"><x-ui.logo :size="34" class="lg:hidden" /><x-ui.logo :size="40" class="max-lg:hidden" /></a>

            <nav class="hidden flex-1 justify-center xl:flex" aria-label="{{ __('Main navigation') }}" data-main-nav>
                <ul class="flex items-center gap-x-7 2xl:gap-x-8">
                    @foreach ($header as $link)
                        <li @class(['relative' => $link['children'] !== []]) @if ($link['children'] !== []) data-dropdown @endif>
                            <span class="flex items-center gap-0.5">
                                <a href="{{ $link['url'] }}" {!! $newTab($link) !!} @if ($isCurrent($link['url'])) aria-current="page" @endif
                                   class="rounded-sm py-2 type-label-md whitespace-nowrap text-fg-secondary transition-colors hover:text-fg aria-[current=page]:text-fg">{{ $link['label'] }}</a>
                                @if ($link['children'] !== [])
                                    <button type="button" data-dropdown-trigger class="rounded-sm p-0.5 text-fg-secondary hover:text-fg aria-expanded:rotate-180" aria-label="{{ __('Show :item links', ['item' => $link['label']]) }}">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                @endif
                            </span>
                            @if ($link['children'] !== [])
                                <ul data-dropdown-menu hidden class="absolute start-0 top-full z-40 mt-3 w-64 rounded-lg border border-line bg-surface-raised p-2 shadow-lg">
                                    @foreach ($link['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" {!! $newTab($child) !!} class="block rounded-md px-3 py-2 type-body-sm text-fg hover:bg-subtle hover:text-fg-brand">{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="ms-auto flex items-center gap-2 lg:gap-3 xl:ms-0">
                <x-language-switch :alternates="$alternates" globe />
                <x-ui.button :href="route('login')" variant="ghost" class="whitespace-nowrap max-lg:hidden">{{ __('Client Portal') }}</x-ui.button>
                <x-ui.button :href="$quoteUrl" class="whitespace-nowrap max-md:hidden" arrow>{{ __('Start Your Project') }}</x-ui.button>
                <button type="button" hidden data-nav-toggle aria-controls="mobile-nav" aria-expanded="false"
                        class="flex size-10 items-center justify-center rounded-md border border-line text-icon-primary hover:bg-subtle aria-expanded:border-line-brand aria-expanded:text-fg-brand xl:hidden">
                    <span class="sr-only" data-nav-label-open>{{ __('Open menu') }}</span>
                    <span class="sr-only" data-nav-label-close hidden>{{ __('Close menu') }}</span>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile navigation: visible without JavaScript; the nav module turns it into a full-height drawer (data-enhanced). --}}
        <nav id="mobile-nav" data-nav-panel class="border-t border-line-subtle bg-surface xl:hidden data-enhanced:absolute data-enhanced:inset-x-0 data-enhanced:top-full data-enhanced:h-[calc(100dvh-4rem)] data-enhanced:overflow-y-auto data-enhanced:shadow-lg" aria-label="{{ __('Mobile navigation') }}">
            <ul class="container-page flex flex-col py-4">
                @foreach ($header as $link)
                    <li class="border-b border-line-subtle last:border-b-0">
                        <a href="{{ $link['url'] }}" {!! $newTab($link) !!} @if ($isCurrent($link['url'])) aria-current="page" @endif class="flex items-center justify-between rounded-md py-3.5 type-label-lg text-fg hover:text-fg-brand aria-[current=page]:text-fg-brand">{{ $link['label'] }}</a>
                        @if ($link['children'] !== [])
                            <ul class="mb-3 flex flex-col border-s-2 border-line-subtle ps-4">
                                @foreach ($link['children'] as $child)
                                    <li><a href="{{ $child['url'] }}" {!! $newTab($child) !!} class="block rounded-md py-2 type-body-md text-fg-secondary hover:text-fg-brand">{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
                <li class="mt-4 flex flex-col gap-3">
                    <x-ui.button :href="$quoteUrl" size="lg" arrow>{{ __('Start Your Project') }}</x-ui.button>
                    <x-ui.button :href="route('login')" variant="secondary" size="lg">{{ __('Client Portal') }}</x-ui.button>
                </li>
            </ul>
        </nav>
    </header>

    <main id="main">{{ $slot }}</main>

    <footer class="dark bg-canvas text-fg">
        <div class="container-page flex flex-col gap-12 pt-14 pb-8 lg:pt-16">
            <div class="flex flex-col gap-10 lg:flex-row lg:justify-between lg:gap-16">
                <div class="flex flex-col gap-5 lg:w-[21.25rem] lg:shrink-0">
                    <x-ui.logo :size="40" class="self-start" />
                    <p class="type-body-sm text-fg-secondary">{{ $tagline }}</p>
                    <ul class="flex gap-2.5" aria-label="{{ __('Contact channels') }}">
                        @if ($whatsapp)
                            <li><a href="{{ \App\Services\ContactChannels::whatsappUrl($whatsapp) }}" target="_blank" rel="noopener noreferrer" class="flex size-10 items-center justify-center rounded-md bg-subtle text-icon-primary transition-colors hover:bg-muted hover:text-fg" aria-label="{{ __('WhatsApp') }}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 20 1.3-4A8.5 8.5 0 1 1 8.1 19z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8A3.5 3.5 0 0 1 10.7 11.5l.8-1-1-2z"/></svg>
                            </a></li>
                        @endif
                        <li><a href="mailto:{{ $email }}" class="flex size-10 items-center justify-center rounded-md bg-subtle text-icon-primary transition-colors hover:bg-muted hover:text-fg" aria-label="{{ __('Email') }}">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </a></li>
                        @if ($phone)
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="flex size-10 items-center justify-center rounded-md bg-subtle text-icon-primary transition-colors hover:bg-muted hover:text-fg" aria-label="{{ __('Phone') }}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>
                            </a></li>
                        @endif
                    </ul>
                </div>
                <div class="grid grid-cols-2 gap-x-8 gap-y-10 sm:grid-cols-4 lg:flex lg:gap-16">
                    @foreach ($footerColumns as $key => $column)
                        <nav aria-labelledby="footer-{{ $key }}" class="flex flex-col gap-3">
                            <h2 id="footer-{{ $key }}" class="type-label-md text-fg">{{ $column['heading'] }}</h2>
                            <ul class="flex flex-col gap-3 type-body-sm">
                                @foreach ($column['items'] as $link)
                                    <li><a href="{{ $link['url'] }}" {!! $newTab($link) !!} class="text-fg-secondary transition-colors hover:text-fg">{{ $link['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    @endforeach
                    <div class="flex flex-col gap-3">
                        <h2 class="type-label-md text-fg">{{ __('Get in touch') }}</h2>
                        <ul class="flex flex-col gap-3 type-body-sm text-fg-secondary">
                            <li><a href="mailto:{{ $email }}" class="transition-colors hover:text-fg" dir="ltr">{{ $email }}</a></li>
                            @if ($phone)
                                <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="transition-colors hover:text-fg" dir="ltr">{{ $phone }}</a></li>
                            @endif
                            @if ($address)
                                <li class="max-w-56">{{ $address }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-4 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="type-body-sm text-fg-tertiary">© {{ now()->year }} {{ __('CodeFanz. All rights reserved.') }}</p>
                @if ($legal !== [] || $consent->isNeeded())
                    <nav aria-label="{{ __('Legal') }}">
                        <ul class="flex flex-wrap gap-x-6 gap-y-2 type-body-sm">
                            @foreach ($legal as $link)
                                <li><a href="{{ $link['url'] }}" {!! $newTab($link) !!} class="text-fg-secondary transition-colors hover:text-fg">{{ $link['label'] }}</a></li>
                            @endforeach
                            @if ($consent->isNeeded())
                                <li><button type="button" hidden data-consent-open class="text-fg-secondary transition-colors hover:text-fg">{{ __('Cookie settings') }}</button></li>
                            @endif
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
    </footer>

    @if ($consent->isNeeded())
        <x-site.consent-banner :measurement-id="$consent->measurementId()" :decided="$consent->hasDecided(request())" />
    @endif
</x-layouts.base>
