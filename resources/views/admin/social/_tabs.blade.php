{{-- Section navigation shared by the social screens (B06 publisher, B07 calendar, accounts, metrics). --}}
@php
    $tabs = [
        ['route' => 'admin.social.posts.index', 'match' => 'admin.social.posts.*', 'label' => __('Posts')],
        ['route' => 'admin.social.calendar', 'match' => 'admin.social.calendar', 'label' => __('Calendar')],
        ['route' => 'admin.social.accounts.index', 'match' => 'admin.social.accounts.*', 'label' => __('Accounts')],
        ['route' => 'admin.social.metrics.index', 'match' => 'admin.social.metrics.*', 'label' => __('Metrics')],
    ];
@endphp
<nav class="flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('Social sections') }}">
    <div class="flex flex-wrap gap-1 rounded-lg bg-subtle p-1">
        @foreach ($tabs as $tab)
            @php($active = request()->routeIs($tab['match']))
            <a href="{{ route($tab['route']) }}" @if ($active) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $active ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-surface' }}">{{ $tab['label'] }}</a>
        @endforeach
    </div>
    {{ $slot ?? '' }}
</nav>
