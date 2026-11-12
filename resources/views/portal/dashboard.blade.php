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

    <x-ui.alert tone="info" :title="__('Your portal is ready')">
        {{ __('Projects, milestones, invoices, documents and messages will appear here as soon as your CodeFanz team adds them. Nothing is shown until it is real.') }}
    </x-ui.alert>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Active project')">
                <x-slot:action><a href="{{ route('portal.projects.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('My Projects') }}</a></x-slot:action>
                @include('portal._empty-module', [
                    'title' => __('No projects yet'),
                    'description' => __('When CodeFanz starts your project you will follow its progress and milestones here.'),
                    'icon' => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
                ])
            </x-ui.card>

            <x-ui.card :title="__('Your account')">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
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

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Recent invoices')">
                <x-slot:action><a href="{{ route('portal.invoices.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('View all') }}</a></x-slot:action>
                @include('portal._empty-module', [
                    'title' => __('No invoices yet'),
                    'description' => __('Invoices and payment requests from CodeFanz will be listed here.'),
                    'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
                ])
            </x-ui.card>

            <x-ui.card :title="__('Messages')">
                <x-slot:action><a href="{{ route('portal.conversations.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Open inbox') }}</a></x-slot:action>
                @include('portal._empty-module', [
                    'title' => __('No messages yet'),
                    'description' => __('Project conversations with your CodeFanz team will appear here.'),
                    'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
                ])
            </x-ui.card>
        </aside>
    </div>
</x-layouts.portal>
