@props(['title', 'crumb' => null])

{{--
    Client portal shell (Figma "Portal — Screens"): Portal Sidebar (nav:<key>), App Header, Mobile App Bar and
    Bottom Tab Bar (tab:<key>). Rendered only behind the `portal` middleware, so the current customer is resolved.
--}}
@php
    $user = auth()->user();
    $currentCustomer = app(\App\Services\CurrentCustomer::class);
    $customer = $currentCustomer->get();
    $switchableCustomers = $currentCustomer->available();
    $unreadNotificationCount = $user->unreadNotifications()->count();
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'projects' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
        'invoices' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
        'payments' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'documents' => '<path d="m6 14 1.45-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.55 6a2 2 0 0 1-1.94 1.5H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H18a2 2 0 0 1 2 2v2"/>',
        'conversations' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'support' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'notifications' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'profile' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'more' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
    $nav = [
        ['key' => 'dashboard', 'label' => __('Dashboard'), 'route' => 'portal.dashboard'],
        ['key' => 'projects', 'label' => __('My Projects'), 'route' => 'portal.projects.index', 'also' => 'portal.projects.*'],
        ['key' => 'invoices', 'label' => __('Invoices'), 'route' => 'portal.invoices.index'],
        ['key' => 'payments', 'label' => __('Payments'), 'route' => 'portal.payments.index'],
        ['key' => 'documents', 'label' => __('Documents'), 'route' => 'portal.documents.index', 'also' => 'portal.documents.*'],
        ['key' => 'conversations', 'label' => __('Conversations'), 'route' => 'portal.conversations.index', 'also' => 'portal.conversations.*'],
        ['key' => 'support', 'label' => __('Support'), 'route' => 'portal.support.index', 'also' => 'portal.support.*'],
        ['key' => 'notifications', 'label' => __('Notifications'), 'route' => 'portal.notifications.index', 'count' => $unreadNotificationCount],
        ['key' => 'profile', 'label' => __('Profile & Settings'), 'route' => 'portal.profile', 'also' => 'portal.security'],
    ];
    $isActive = fn (array $item): bool => request()->routeIs($item['route']) || (isset($item['also']) && request()->routeIs($item['also']));
    $tabs = ['dashboard', 'projects', 'payments', 'conversations'];
    $tabLabels = ['dashboard' => __('Home'), 'projects' => __('Projects'), 'payments' => __('Payments'), 'conversations' => __('Messages')];
    $navByKey = collect($nav)->keyBy('key');
    $moreActive = collect($nav)->reject(fn ($item) => in_array($item['key'], $tabs, true))->contains(fn ($item) => $isActive($item));
@endphp

<x-layouts.base :themeable="true" :title="$title">
    <div class="flex min-h-screen">
        {{-- Portal Sidebar --}}
        <aside class="sticky top-0 hidden h-screen w-68 shrink-0 flex-col border-e border-line bg-surface lg:flex" data-portal-sidebar>
            <div class="flex flex-col gap-2 px-5 pt-5 pb-4">
                <a href="{{ route('portal.dashboard') }}" class="w-fit"><x-ui.logo /></a>
                <span class="w-fit rounded-full bg-accent-subtle px-2.5 py-0.5 text-xs font-semibold text-fg-accent">{{ __('Client Portal') }}</span>
            </div>

            @if ($switchableCustomers->count() > 1)
                <form method="POST" action="{{ route('portal.account.switch') }}" class="px-4 pb-3" data-auto-submit>
                    @csrf
                    <label for="sidebar-customer" class="mb-1 block text-xs font-semibold text-fg-tertiary">{{ __('Account') }}</label>
                    <select id="sidebar-customer" name="customer" class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">
                        @foreach ($switchableCustomers as $option)
                            <option value="{{ $option->ulid }}" @selected($option->is($customer))>{{ $option->company_name }}</option>
                        @endforeach
                    </select>
                    <noscript><x-ui.button size="sm" variant="secondary" class="mt-2">{{ __('Switch account') }}</x-ui.button></noscript>
                </form>
            @endif

            <nav class="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3" aria-label="{{ __('Portal navigation') }}">
                @foreach ($nav as $item)
                    @php($active = $isActive($item))
                    <a href="{{ route($item['route']) }}" data-nav="{{ $item['key'] }}" @if ($active) aria-current="page" @endif
                       class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold {{ $active ? 'bg-brand-subtle text-fg-brand' : 'text-fg-secondary hover:bg-subtle' }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0">{!! $icons[$item['key']] !!}</svg>
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if (! empty($item['count']))
                            <span class="rounded-full bg-danger-subtle px-2 py-0.5 text-xs font-semibold text-fg-danger">{{ $item['count'] > 99 ? '99+' : $item['count'] }}<span class="sr-only"> {{ __('unread') }}</span></span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="mx-4 mb-4 rounded-xl bg-brand-subtle p-4">
                <p class="text-sm font-semibold text-fg">{{ __('Need help?') }}</p>
                <p class="mt-1 text-xs text-fg-secondary">{{ __('Our team replies within one business day.') }}</p>
                <a href="{{ route('contact', ['locale' => app()->getLocale()]) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-fg-brand hover:underline">
                    {{ __('Contact CodeFanz') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span>
                </a>
            </div>

            <div class="flex items-center gap-3 border-t border-line px-4 py-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-sm font-semibold text-fg-brand" aria-hidden="true">{{ $user->initials() }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-fg" dir="auto">{{ $user->name }}</p>
                    <p class="truncate text-xs text-fg-tertiary" dir="auto" data-current-customer>{{ $customer->company_name }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex size-9 items-center justify-center rounded-md text-fg-secondary hover:bg-subtle" aria-label="{{ __('Sign out') }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="rtl:-scale-x-100"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17-5-5 5-5"/><path d="M5 12h12"/></svg>
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- App Header (desktop) / Mobile App Bar --}}
            <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-line bg-surface px-4 py-3 lg:static lg:px-8 lg:py-4">
                <a href="{{ route('portal.dashboard') }}" class="lg:hidden" aria-label="{{ __('Dashboard') }}"><x-ui.logo mark :size="32" /></a>
                <div class="min-w-0 flex-1">
                    <p class="hidden text-xs text-fg-tertiary lg:block">{{ __('Client Portal') }}@if ($crumb) <span aria-hidden="true" class="rtl:-scale-x-100 inline-block">›</span> {{ $crumb }}@endif</p>
                    <h1 class="truncate text-lg font-semibold text-fg lg:text-xl">{{ $title }}</h1>
                </div>
                <div class="hidden sm:block"><x-language-switch /></div>
                <button type="button" data-theme-toggle class="hidden size-10 items-center justify-center rounded-md border border-line text-fg hover:bg-subtle sm:flex" aria-label="{{ __('Switch light or dark theme') }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>
                <a href="{{ route('portal.notifications.index') }}" class="relative flex size-10 items-center justify-center rounded-md border border-line text-fg hover:bg-subtle"
                   aria-label="{{ $unreadNotificationCount ? __('Notifications (:count unread)', ['count' => $unreadNotificationCount]) : __('Notifications') }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['notifications'] !!}</svg>
                    @if ($unreadNotificationCount)
                        <span class="absolute -end-1 -top-1 size-2.5 rounded-full bg-danger" aria-hidden="true"></span>
                    @endif
                </a>
                <div data-dropdown class="relative">
                    <button type="button" data-dropdown-trigger class="flex size-10 items-center justify-center rounded-full bg-brand-subtle text-sm font-semibold text-fg-brand" aria-label="{{ __('Account menu') }}">{{ $user->initials() }}</button>
                    <div data-dropdown-menu hidden class="absolute end-0 z-40 mt-2 w-64 rounded-lg border border-line bg-surface-raised p-1.5 shadow-lg">
                        <div class="px-3 py-2">
                            <p class="truncate text-sm font-semibold text-fg" dir="auto">{{ $user->name }}</p>
                            <p class="truncate text-xs text-fg-tertiary" dir="auto">{{ $customer->company_name }}</p>
                        </div>
                        @if ($switchableCustomers->count() > 1)
                            <div class="border-t border-line-subtle px-3 py-2 lg:hidden">
                                <p class="mb-1 text-xs font-semibold text-fg-tertiary">{{ __('Switch account') }}</p>
                                @foreach ($switchableCustomers as $option)
                                    @continue($option->is($customer))
                                    <form method="POST" action="{{ route('portal.account.switch') }}">
                                        @csrf
                                        <input type="hidden" name="customer" value="{{ $option->ulid }}">
                                        <button type="submit" class="w-full rounded-md px-2 py-1.5 text-start text-sm text-fg hover:bg-subtle" dir="auto">{{ $option->company_name }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                        <a href="{{ route('portal.profile') }}" class="block rounded-md px-3 py-2 text-sm text-fg hover:bg-subtle">{{ __('Profile & Settings') }}</a>
                        <a href="{{ route('portal.security') }}" class="block rounded-md px-3 py-2 text-sm text-fg hover:bg-subtle">{{ __('Security') }}</a>
                        <div class="flex items-center justify-between gap-2 border-t border-line-subtle px-1 py-1 sm:hidden">
                            <x-language-switch />
                            <button type="button" data-theme-toggle class="rounded-md px-2.5 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">{{ __('Theme') }}</button>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-md px-3 py-2 text-start text-sm text-fg-danger hover:bg-subtle">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </div>
            </header>

            <main id="main" class="flex flex-col gap-6 px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:pb-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Bottom Tab Bar (mobile) --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface lg:hidden" aria-label="{{ __('Portal sections') }}" data-portal-tabs>
        <ul class="grid grid-cols-5">
            @foreach ($tabs as $key)
                @php($item = $navByKey[$key])
                @php($active = $isActive($item))
                <li>
                    <a href="{{ route($item['route']) }}" data-tab="{{ $key }}" @if ($active) aria-current="page" @endif
                       class="flex flex-col items-center gap-0.5 px-1 py-2 text-[11px] font-semibold {{ $active ? 'text-fg-brand' : 'text-fg-tertiary' }}">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$key] !!}</svg>
                        {{ $tabLabels[$key] }}
                    </a>
                </li>
            @endforeach
            <li data-dropdown class="relative">
                <button type="button" data-dropdown-trigger data-tab="more"
                        class="flex w-full flex-col items-center gap-0.5 px-1 py-2 text-[11px] font-semibold {{ $moreActive ? 'text-fg-brand' : 'text-fg-tertiary' }}">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['more'] !!}</svg>
                    {{ __('More') }}
                </button>
                <div data-dropdown-menu hidden class="absolute end-2 bottom-full z-40 mb-2 w-60 rounded-lg border border-line bg-surface-raised p-1.5 shadow-lg">
                    @foreach ($nav as $item)
                        @continue(in_array($item['key'], $tabs, true))
                        <a href="{{ route($item['route']) }}" @if ($isActive($item)) aria-current="page" @endif
                           class="flex items-center gap-3 rounded-md px-3 py-2 text-sm {{ $isActive($item) ? 'bg-brand-subtle font-semibold text-fg-brand' : 'text-fg hover:bg-subtle' }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$item['key']] !!}</svg>
                            <span class="flex-1">{{ $item['label'] }}</span>
                            @if (! empty($item['count']))
                                <span class="rounded-full bg-danger-subtle px-2 py-0.5 text-xs font-semibold text-fg-danger">{{ $item['count'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </li>
        </ul>
    </nav>
</x-layouts.base>
