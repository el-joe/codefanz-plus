<nav class="flex flex-wrap gap-2" aria-label="{{ __('Freelance Radar sections') }}">
    @php
        $links = [['admin.radar.index', __('Projects'), 'radar.view'], ['admin.radar.analytics', __('Analytics'), 'radar.view'], ['admin.radar.settings', __('Settings & filters'), 'radar.manage']];
    @endphp
    @foreach ($links as [$route, $label, $permission])
        @can($permission)
            <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm font-semibold {{ request()->routeIs($route) ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-subtle' }}">{{ $label }}</a>
        @endcan
    @endforeach
</nav>
