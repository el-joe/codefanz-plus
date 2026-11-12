{{-- A12 tabs: Audit · Meta editor · Redirects · Sitemap & robots (separate pages, shared header). --}}
@php
    $tabs = [
        'admin.seo.audit' => __('Audit'),
        'admin.seo.meta' => __('Meta editor'),
        'admin.redirects.index' => __('Redirects'),
        'admin.seo.sitemap' => __('Sitemap & robots'),
    ];
@endphp
<nav aria-label="{{ __('SEO sections') }}" class="flex flex-wrap gap-1 rounded-lg bg-subtle p-1">
    @foreach ($tabs as $route => $label)
        @php($active = request()->routeIs($route) || ($route === 'admin.redirects.index' && request()->routeIs('admin.redirects.*')))
        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
           class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $active ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-surface' }}">{{ $label }}</a>
    @endforeach
</nav>
