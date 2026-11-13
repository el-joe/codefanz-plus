@props(['title', 'crumb' => null])

@php
    $user = auth()->user();
    $nav = [
        ['label' => __('Overview'), 'route' => 'admin.dashboard', 'can' => 'admin.access'],
        ['label' => __('Approvals'), 'route' => 'admin.approvals.index', 'can' => 'viewAny', 'model' => \App\Models\ChangeRequest::class, 'count' => $pendingApprovalsCount ?? null],
        ['label' => __('Leads'), 'route' => 'admin.leads.index', 'can' => 'viewAny', 'model' => \App\Models\Lead::class, 'also' => 'admin.leads.*'],
        ['label' => __('Customers'), 'route' => 'admin.customers.index', 'can' => 'viewAny', 'model' => \App\Models\Customer::class],
        ['label' => __('Projects'), 'route' => 'admin.projects.index', 'can' => 'viewAny', 'model' => \App\Models\Project::class],
        ['label' => __('Payments'), 'route' => 'admin.payments.index', 'can' => 'viewAny', 'model' => \App\Models\Payment::class, 'also' => ['admin.payment-links.*', 'admin.invoices.*']],
        ['label' => __('Support tickets'), 'route' => 'admin.tickets.index', 'can' => 'viewAny', 'model' => \App\Models\SupportTicket::class],
        ['label' => __('Contact inbox'), 'route' => 'admin.contact-messages.index', 'can' => 'leads.view'],
        ['label' => __('Pages'), 'route' => 'admin.pages.index', 'can' => 'viewAny', 'model' => \App\Models\Page::class],
        ['label' => __('Page sections'), 'route' => 'admin.home-sections.index', 'can' => 'viewAny', 'model' => \App\Models\PageSection::class],
        ['label' => __('Navigation & menus'), 'route' => 'admin.menus.index', 'can' => 'viewAny', 'model' => \App\Models\MenuItem::class],
        ['label' => __('Blog'), 'route' => 'admin.posts.index', 'can' => 'viewAny', 'model' => \App\Models\Post::class, 'also' => ['admin.post-categories.*', 'admin.tags.*']],
        ['label' => __('Services'), 'route' => 'admin.services.index', 'can' => 'viewAny', 'model' => \App\Models\Service::class],
        ['label' => __('Technologies'), 'route' => 'admin.technologies.index', 'can' => 'viewAny', 'model' => \App\Models\Technology::class, 'also' => 'admin.technology-categories.*'],
        ['label' => __('Industries'), 'route' => 'admin.industries.index', 'can' => 'viewAny', 'model' => \App\Models\Industry::class],
        ['label' => __('Portfolio'), 'route' => 'admin.portfolio.index', 'can' => 'viewAny', 'model' => \App\Models\PortfolioProject::class, 'also' => 'admin.portfolio-categories.*'],
        ['label' => __('Software products'), 'route' => 'admin.products.index', 'can' => 'viewAny', 'model' => \App\Models\Product::class],
        ['label' => __('Testimonials'), 'route' => 'admin.testimonials.index', 'can' => 'viewAny', 'model' => \App\Models\Testimonial::class],
        ['label' => __('FAQ'), 'route' => 'admin.faqs.index', 'can' => 'viewAny', 'model' => \App\Models\Faq::class, 'also' => 'admin.faq-categories.*'],
        ['label' => __('Statistics'), 'route' => 'admin.statistics.index', 'can' => 'viewAny', 'model' => \App\Models\Statistic::class],
        ['label' => __('Careers'), 'route' => 'admin.careers.index', 'can' => 'viewAny', 'model' => \App\Models\Career::class],
        ['label' => __('Media'), 'route' => 'admin.media.index', 'can' => 'media.manage'],
        ['label' => __('SEO'), 'route' => 'admin.seo.audit', 'can' => 'seo.manage', 'also' => ['admin.seo.*', 'admin.redirects.*']],
        ['label' => __('Analytics'), 'route' => 'admin.analytics.index', 'can' => 'seo.manage'],
        ['label' => __('AI Studio'), 'route' => 'admin.ai-studio.index', 'can' => 'ai.use', 'also' => ['admin.ai-studio.*', 'admin.prompts.*', 'admin.brand-voice.*']],
        ['label' => __('Social'), 'route' => 'admin.social.posts.index', 'can' => 'social.manage', 'also' => 'admin.social.*'],
        ['label' => __('AI governance'), 'route' => 'admin.ai-governance', 'can' => 'automation.manage'],
        ['label' => __('MCP'), 'route' => 'admin.mcp.index', 'can' => 'mcp.manage', 'also' => 'admin.mcp.*'],
        ['label' => __('Emergency controls'), 'route' => 'admin.emergency.index', 'can' => 'automation.manage'],
        ['label' => __('AI usage'), 'route' => 'admin.ai-usage', 'can' => 'settings.manage'],
        ['label' => __('Users & roles'), 'route' => 'admin.users.index', 'can' => 'viewAny', 'model' => \App\Models\User::class, 'also' => 'admin.roles.*'],
        ['label' => __('Settings'), 'route' => 'admin.settings.index', 'can' => 'settings.manage', 'also' => 'admin.settings.*'],
        ['label' => __('Audit logs'), 'route' => 'admin.audit-logs.index', 'can' => 'audit.view'],
    ];
    $unreadNotificationCount = $user->unreadNotifications()->count();
    $recentNotifications = $user->notifications()->limit(8)->get();
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
                <div data-dropdown class="relative" data-notification-bell>
                    <button type="button" data-dropdown-trigger class="relative flex size-10 items-center justify-center rounded-md border border-line text-fg hover:bg-subtle"
                            aria-label="{{ $unreadNotificationCount ? __('Notifications (:count unread)', ['count' => $unreadNotificationCount]) : __('Notifications') }}">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        @if ($unreadNotificationCount)
                            <span class="absolute -end-1.5 -top-1.5 flex min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[11px] font-semibold text-fg-on-brand" data-unread-count aria-hidden="true">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                        @endif
                    </button>
                    <div data-dropdown-menu hidden class="absolute end-0 z-40 mt-2 w-80 rounded-lg border border-line bg-surface-raised p-1.5 shadow-lg">
                        <div class="flex items-center justify-between gap-2 px-3 py-2">
                            <p class="text-sm font-semibold text-fg">{{ __('Notifications') }}</p>
                            @if ($unreadNotificationCount)
                                <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-fg-brand hover:underline">{{ __('Mark all as read') }}</button>
                                </form>
                            @endif
                        </div>
                        @forelse ($recentNotifications as $notification)
                            <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="flex w-full items-start gap-2 rounded-md px-3 py-2 text-start text-sm hover:bg-subtle">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-brand' }}" aria-hidden="true"></span>
                                    <span class="flex min-w-0 flex-col">
                                        <span class="text-fg {{ $notification->read_at ? '' : 'font-semibold' }}" dir="auto">@include('admin.notifications._text', ['notification' => $notification])</span>
                                        <span class="text-xs text-fg-tertiary">{{ $notification->created_at->diffForHumans() }}@unless ($notification->read_at) · <span class="sr-only">{{ __('Unread') }}</span>@endunless</span>
                                    </span>
                                </button>
                            </form>
                        @empty
                            <p class="px-3 py-4 text-sm text-fg-secondary">{{ __('You’re all caught up.') }}</p>
                        @endforelse
                        <a href="{{ route('admin.notifications.index') }}" class="block rounded-md px-3 py-2 text-center text-sm font-semibold text-fg-brand hover:bg-subtle">{{ __('View all notifications') }}</a>
                    </div>
                </div>
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
