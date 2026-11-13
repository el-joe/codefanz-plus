@php
    $hour = now()->setTimezone(auth()->user()->timezone ?: config('app.timezone'))->hour;
    $greeting = match (true) {
        $hour < 12 => __('Good morning, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')]),
        $hour < 18 => __('Good afternoon, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')]),
        default => __('Good evening, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')]),
    };
@endphp

<x-layouts.portal :title="__('Dashboard')" :crumb="__('Dashboard')">
    <div>
        <h2 class="text-2xl font-semibold text-fg sm:text-3xl" dir="auto">{{ $greeting }}</h2>
        <p class="mt-1 text-base text-fg-secondary">{{ __('Here’s what’s happening with your projects.') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-dashboard-kpis>
        @foreach ([
            ['projects', __('Active projects'), $activeProjectCount, route('portal.projects.index')],
            ['tickets', __('Open tickets'), $openTicketCount, route('portal.support.index')],
            ['messages', __('Unread messages'), $unreadMessageCount, route('portal.conversations.index')],
        ] as [$key, $label, $value, $url])
            <a href="{{ $url }}" class="rounded-xl border border-line bg-surface p-4 hover:border-line-strong" data-kpi="{{ $key }}">
                <p class="text-xs text-fg-tertiary">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-fg">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Active project')">
                <x-slot:action><a href="{{ route('portal.projects.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('My Projects') }}</a></x-slot:action>
                @if ($activeProject)
                    @php($progress = $activeProject->progressPercent())
                    <a href="{{ route('portal.projects.show', $activeProject) }}" class="block" data-active-project>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :tone="$activeProject->status->tone()">{{ $activeProject->status->label() }}</x-ui.badge>
                            <span class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $activeProject->reference }}</span>
                        </div>
                        <p class="mt-2 text-lg font-semibold text-fg" dir="auto">{{ $activeProject->name }}</p>
                        <div class="mt-3 mb-1 flex justify-between text-xs text-fg-secondary"><span>{{ __('Progress') }}</span><span class="font-semibold text-fg">{{ $progress }}%</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Progress') }}"><div class="h-full rounded-full bg-brand" style="width: {{ $progress }}%"></div></div>
                    </a>
                @else
                    @include('portal._empty-module', [
                        'title' => __('No projects yet'),
                        'description' => __('When CodeFanz starts your project you will follow its progress and milestones here.'),
                        'icon' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
                    ])
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Upcoming milestones')">
                @if ($upcomingMilestones->isEmpty())
                    <p class="text-sm text-fg-secondary">{{ __('No upcoming milestones.') }}</p>
                @else
                    <ul class="flex flex-col divide-y divide-line-subtle" data-upcoming-milestones>
                        @foreach ($upcomingMilestones as $milestone)
                            <li class="flex flex-wrap items-center gap-3 py-2.5">
                                <a href="{{ route('portal.projects.show', ['project' => $milestone->project, 'tab' => 'milestones']) }}" class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-fg" dir="auto">{{ $milestone->title }}</span>
                                    <span class="block truncate text-xs text-fg-tertiary" dir="auto">{{ $milestone->project->name }}</span>
                                </a>
                                <span class="text-xs text-fg-secondary">{{ $milestone->due_date?->translatedFormat('j M') ?? '—' }}</span>
                                <x-ui.badge :tone="$milestone->status->tone()">{{ $milestone->status === \App\Enums\MilestoneStatus::AwaitingApproval ? __('Awaiting your approval') : $milestone->status->label() }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Your account')">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-fg-tertiary">{{ __('Customer') }}</dt>
                        <dd class="mt-0.5 font-semibold text-fg" dir="auto">{{ $customer->company_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-fg-tertiary">{{ __('Customer reference') }}</dt>
                        <dd class="mt-0.5 font-mono font-semibold text-fg" dir="ltr">{{ $customer->reference }}</dd>
                    </div>
                    <div>
                        <dt class="text-fg-tertiary">{{ __('Unread notifications') }}</dt>
                        <dd class="mt-0.5 font-semibold text-fg">{{ $unreadNotificationCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-fg-tertiary">{{ __('Signed in as') }}</dt>
                        <dd class="mt-0.5 truncate font-semibold text-fg" dir="ltr">{{ auth()->user()->email }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Recent invoices')">
                <x-slot:action><a href="{{ route('portal.invoices.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('View all') }}</a></x-slot:action>
                @if ($recentInvoices->isEmpty())
                    @include('portal._empty-module', [
                        'title' => __('No invoices yet'),
                        'description' => __('Invoices and payment requests from CodeFanz will be listed here.'),
                        'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
                    ])
                @else
                    <ul class="flex flex-col divide-y divide-line-subtle" data-recent-invoices>
                        @foreach ($recentInvoices as $invoice)
                            <li class="flex items-center gap-3 py-2.5">
                                <a href="{{ route('portal.invoices.print', $invoice) }}" class="min-w-0 flex-1">
                                    <span class="block font-mono text-xs font-semibold text-fg" dir="ltr">{{ $invoice->number }}</span>
                                    <span class="block text-sm font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($invoice->total_minor, $invoice->currency) }}</span>
                                </a>
                                @if ($invoice->status === \App\Enums\InvoiceStatus::Issued && $invoice->paymentLink?->isPayable())
                                    <x-ui.button size="sm" :href="route('pay.show', $invoice->paymentLink)">{{ __('Pay') }}</x-ui.button>
                                @else
                                    <x-ui.badge :tone="$invoice->isOverdue() ? 'danger' : $invoice->status->tone()">{{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}</x-ui.badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Messages')">
                <x-slot:action><a href="{{ route('portal.conversations.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Open inbox') }}</a></x-slot:action>
                @if ($recentConversations->isEmpty())
                    @include('portal._empty-module', [
                        'title' => __('No messages yet'),
                        'description' => __('Project conversations with your CodeFanz team will appear here.'),
                        'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
                    ])
                @else
                    <ul class="flex flex-col divide-y divide-line-subtle" data-recent-conversations>
                        @foreach ($recentConversations as $conversation)
                            <li>
                                <a href="{{ route('portal.conversations.show', $conversation) }}" class="flex items-center gap-2 py-2.5">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-fg" dir="auto">{{ $conversation->project->name }} · {{ $conversation->displaySubject() }}</span>
                                        @if ($conversation->latestMessage)
                                            <span class="block truncate text-xs text-fg-secondary" dir="auto">{{ \Illuminate\Support\Str::limit($conversation->latestMessage->body, 60) }}</span>
                                        @endif
                                    </span>
                                    @if ($conversation->unread_count > 0)
                                        <span class="rounded-full bg-brand-subtle px-2 py-0.5 text-xs font-semibold text-fg-brand">{{ $conversation->unread_count }}<span class="sr-only"> {{ __('unread') }}</span></span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.portal>
