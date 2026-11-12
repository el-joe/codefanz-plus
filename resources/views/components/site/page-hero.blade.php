{{-- Public page header: breadcrumb, eyebrow, title and lead. $crumbs = [label => url|null] after "Home". --}}
@props(['eyebrow' => null, 'title', 'lead' => null, 'crumbs' => []])

<section class="bg-[radial-gradient(ellipse_at_bottom_left,rgb(110_86_255/0.12),transparent_60%)] bg-subtle">
    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-12 lg:px-20 lg:py-16">
        <nav aria-label="{{ __('Breadcrumb') }}">
            <ol class="flex flex-wrap items-center gap-2 text-sm text-fg-secondary">
                <li><a href="{{ route('home') }}" class="hover:text-fg-brand">{{ __('Home') }}</a></li>
                @foreach ($crumbs as $label => $url)
                    <li class="flex items-center gap-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="rtl:-scale-x-100"><path d="m9 6 6 6-6 6"/></svg>
                        @if ($url)
                            <a href="{{ $url }}" class="hover:text-fg-brand">{{ $label }}</a>
                        @else
                            <span aria-current="page" class="text-fg">{{ $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
        {{ $before ?? '' }}
        @if ($eyebrow)
            <p class="text-xs font-bold uppercase tracking-wider text-fg-brand">{{ $eyebrow }}</p>
        @endif
        <h1 class="max-w-3xl text-4xl font-extrabold leading-tight tracking-tight text-fg lg:text-5xl">{{ $title }}</h1>
        @if ($lead)
            <p class="max-w-2xl text-lg text-fg-secondary">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
