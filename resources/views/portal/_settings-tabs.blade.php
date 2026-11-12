<nav class="flex gap-6 overflow-x-auto border-b border-line" aria-label="{{ __('Profile & Settings') }}">
    @foreach (['portal.profile' => __('Profile'), 'portal.security' => __('Security')] as $route => $label)
        <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
           class="-mb-px whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold {{ request()->routeIs($route) ? 'border-brand text-fg-brand' : 'border-transparent text-fg-secondary hover:text-fg' }}">{{ $label }}</a>
    @endforeach
</nav>
