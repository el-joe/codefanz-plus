<nav class="flex flex-wrap gap-2" aria-label="{{ __('Users & roles') }}">
    @foreach (['admin.users.index' => __('Users'), 'admin.roles.index' => __('Roles & permissions')] as $routeName => $label)
        @php($active = request()->routeIs($routeName))
        <a href="{{ route($routeName) }}" @if ($active) aria-current="page" @endif
           class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $active ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-subtle' }}">{{ $label }}</a>
    @endforeach
</nav>
