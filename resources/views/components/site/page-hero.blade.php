{{--
    Public page header (Design v2): breadcrumb, eyebrow, title and lead on the lavender tint band with a faint grid and a
    brand glow at the top-end. $crumbs = [label => url|null] after "Home".
    image = a SiteImages name (default about-glass-stack) shown as a 440×320 accent beside the copy on desktop
    (hidden below lg, where the hero is a single column); false = text only.
    align = start (default) | center (Figma Blog hero: centred copy and search, no image, breadcrumb kept for assistive technology only).
--}}
@props(['eyebrow' => null, 'title', 'lead' => null, 'crumbs' => [], 'align' => 'start', 'image' => 'about-glass-stack'])

@php
    $centered = $align === 'center';
    $accent = $centered ? null : ($image ?: null);
@endphp

<section class="relative isolate overflow-hidden border-b border-v2-hairline bg-v2-tint">
    <div class="pointer-events-none absolute inset-0 -z-10 bg-grid-v2 opacity-60" aria-hidden="true"></div>
    <div @class([
        'pointer-events-none absolute -z-10 size-[40rem] rounded-full bg-[radial-gradient(closest-side,rgb(110_86_255/0.16),transparent)]',
        '-end-40 -top-64' => ! $centered,
        'start-1/2 -bottom-96 -translate-x-1/2 rtl:translate-x-1/2 bg-[radial-gradient(closest-side,rgb(31_214_196/0.14),transparent)]' => $centered,
    ]) aria-hidden="true"></div>
    <div @class(['container-page flex flex-col gap-10 py-12 lg:flex-row lg:items-center lg:justify-between lg:gap-16 lg:py-20', 'items-center text-center lg:flex-col lg:pt-16 lg:pb-12' => $centered])>
        <div @class(['flex min-w-0 flex-col gap-5', 'items-center' => $centered, 'lg:max-w-[45rem] lg:flex-1' => $accent, 'max-w-4xl' => ! $centered && ! $accent])>
            <nav aria-label="{{ __('Breadcrumb') }}" @class(['sr-only' => $centered])>
                <ol class="flex flex-wrap items-center gap-2 type-body-sm text-fg-tertiary">
                    <li><a href="{{ route('home') }}" class="rounded-sm hover:text-fg-brand">{{ __('Home') }}</a></li>
                    @foreach ($crumbs as $label => $url)
                        <li class="flex items-center gap-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-icon-secondary rtl:-scale-x-100"><path d="m9 6 6 6-6 6"/></svg>
                            @if ($url)
                                <a href="{{ $url }}" class="rounded-sm hover:text-fg-brand">{{ $label }}</a>
                            @else
                                <span aria-current="page" class="type-label-md text-fg">{{ $label }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            {{ $before ?? '' }}
            @if ($eyebrow)
                <p class="type-eyebrow text-fg-brand">{{ $eyebrow }}</p>
            @endif
            <h1 @class(['type-h1 text-balance text-v2-display lg:type-v2-h1', 'max-w-5xl' => $centered])>{{ $title }}</h1>
            @if ($lead)
                <p class="max-w-3xl type-body-lg text-fg-secondary lg:type-lead">{{ $lead }}</p>
            @endif
            {{ $slot }}
        </div>
        @if ($accent)
            <div class="hidden w-[27.5rem] shrink-0 overflow-hidden rounded-[1.75rem] border border-v2-hairline bg-surface shadow-v2-card lg:block">
                <x-site.image :name="$accent" sizes="440px" described class="aspect-[11/8] size-full object-cover" />
            </div>
        @endif
    </div>
</section>
@php($structuredData = app(\App\Services\Seo\StructuredData::class))
{{ $structuredData->script($structuredData->breadcrumbs([__('Home') => route('home'), ...$crumbs])) }}
