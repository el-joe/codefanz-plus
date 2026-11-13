{{--
    Public page header: breadcrumb, eyebrow, title and lead on the canvas band with the brand glow. $crumbs = [label => url|null] after "Home".
    align = start (default) | center (Figma Blog hero: centred copy and search, breadcrumb kept for assistive technology only).
--}}
@props(['eyebrow' => null, 'title', 'lead' => null, 'crumbs' => [], 'align' => 'start'])

@php($centered = $align === 'center')

<section @class([
    'border-b border-line-subtle bg-canvas',
    'bg-[radial-gradient(50rem_17rem_at_5rem_100%,rgb(110_86_255/0.14),transparent)] rtl:bg-[radial-gradient(50rem_17rem_at_calc(100%-5rem)_100%,rgb(110_86_255/0.14),transparent)]' => ! $centered,
    'bg-[radial-gradient(60rem_12rem_at_50%_100%,rgb(31_214_196/0.12),transparent)]' => $centered,
])>
    <div @class(['container-page flex flex-col gap-5 py-12 lg:py-20', 'items-center text-center lg:pt-16 lg:pb-12' => $centered])>
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
            <p class="type-overline text-fg-brand">{{ $eyebrow }}</p>
        @endif
        <h1 @class(['type-h1 text-balance text-fg lg:type-display-lg', 'max-w-4xl' => ! $centered, 'max-w-5xl' => $centered])>{{ $title }}</h1>
        @if ($lead)
            <p class="max-w-3xl type-body-lg text-fg-secondary">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
@php($structuredData = app(\App\Services\Seo\StructuredData::class))
{{ $structuredData->script($structuredData->breadcrumbs([__('Home') => route('home'), ...$crumbs])) }}
