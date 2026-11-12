@props(['title', 'crumb' => null])

@php
    $user = auth()->user();
    $nav = [
        ['label' => __('Overview'), 'route' => 'admin.dashboard', 'can' => 'admin.access'],
        ['label' => __('Approvals'), 'route' => 'admin.approvals.index', 'can' => 'viewAny', 'model' => \App\Models\ChangeRequest::class, 'count' => $pendingApprovalsCount ?? null],
        ['label' => __('Pages'), 'route' => 'admin.pages.index', 'can' => 'viewAny', 'model' => \App\Models\Page::class],
        ['label' => __('Services'), 'route' => 'admin.services.index', 'can' => 'viewAny', 'model' => \App\Models\Service::class],
        ['label' => __('Technologies'), 'route' => 'admin.technologies.index', 'can' => 'viewAny', 'model' => \App\Models\Technology::class, 'also' => 'admin.technology-categories.*'],
        ['label' => __('Industries'), 'route' => 'admin.industries.index', 'can' => 'viewAny', 'model' => \App\Models\Industry::class],
        ['label' => __('Media'), 'route' => 'admin.media.index', 'can' => 'media.manage'],
        ['label' => __('Emergency controls'), 'route' => 'admin.emergency.index', 'can' => 'automation.manage'],
        ['label' => __('Users & roles'), 'route' => 'admin.users.index', 'can' => 'viewAny', 'model' => \App\Models\User::class, 'also' => 'admin.roles.*'],
        ['label' => __('Settings'), 'route' => 'admin.settings.index', 'can' => 'settings.manage', 'also' => 'admin.settings.*'],
        ['label' => __('Audit logs'), 'route' => 'admin.audit-logs.index', 'can' => 'audit.view'],
    ];
@endphp

<x-layouts.base :title="$title" entry="resources/js/admin.js">
    <div class="flex min-h-screen">
        <aside class="sticky top-0 hidden h-screen w-68 shrink-0 flex-col border-e border-line bg-surface lg:flex">
            <div class="px-5 py-5"><x-ui.logo /></div>
            <nav class="flex flex-1 flex-col gap-0.5 px-3" aria-label="{{ __('Admin navigation') }}">
                @foreach ($nav as $item)
                    @continue(isset($item['model']) ? ! $user->can($item['can'], $item['model']) : ! $user->can($item['can']))
                    @php($active = request()->routeIs($item['route'].'*') || request()->routeIs(str_replace('.index', '.*', $item['route'])) || (isset($item['also']) && request()->routeIs($item['also'])))
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                       class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold {{ $active ? 'bg-brand-subtle text-fg-brand' : 'text-fg-secondary hover:bg-subtle' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="flex items-center gap-3 border-t border-line px-4 py-4">
                <span class="flex size-10 items-center justify-center rounded-full bg-brand-subtle text-sm font-semibold text-fg-brand" aria-hidden="true">{{ $user->initials() }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-fg">{{ $user->name }}</p>
                    <p class="truncate text-xs text-fg-tertiary">{{ $user->getRoleNames()->map(fn ($role) => __(\Illuminate\Support\Str::headline($role)))->implode(', ') }}</p>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center gap-4 border-b border-line bg-surface px-5 py-4 lg:px-8">
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-fg-tertiary">{{ __('Admin') }}@if ($crumb) <span aria-hidden="true">›</span> {{ $crumb }}@endif</p>
                    <h1 class="truncate text-xl font-semibold text-fg">{{ $title }}</h1>
                </div>
                <x-language-switch />
                <button type="button" data-theme-toggle class="rounded-md border border-line px-3 py-2 text-sm font-semibold text-fg hover:bg-subtle">{{ __('Theme') }}</button>
                <div data-dropdown class="relative">
                    <button type="button" data-dropdown-trigger class="flex size-10 items-center justify-center rounded-full bg-brand-subtle text-sm font-semibold text-fg-brand" aria-label="{{ __('Account menu') }}">{{ $user->initials() }}</button>
                    <div data-dropdown-menu hidden class="absolute end-0 z-40 mt-2 w-56 rounded-lg border border-line bg-surface-raised p-1.5 shadow-lg">
                        <a href="{{ route('admin.profile') }}" class="block rounded-md px-3 py-2 text-sm text-fg hover:bg-subtle">{{ __('Profile') }}</a>
                        <a href="{{ route('admin.security') }}" class="block rounded-md px-3 py-2 text-sm text-fg hover:bg-subtle">{{ __('Security') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-md px-3 py-2 text-start text-sm text-fg-danger hover:bg-subtle">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </div>
            </header>
            <main id="main" class="flex flex-col gap-6 px-5 py-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
