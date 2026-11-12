@php
    $groups = $notifications->getCollection()->groupBy(fn ($notification) => $notification->created_at->isToday() ? 'today' : 'earlier');
@endphp

<x-layouts.portal :title="__('Notifications')" :crumb="__('Notifications')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex gap-1 rounded-lg bg-subtle p-1" aria-label="{{ __('Filter notifications') }}">
            @foreach (['all' => __('All'), 'unread' => __('Unread (:count)', ['count' => $unreadCount])] as $key => $label)
                <a href="{{ route('portal.notifications.index', $key === 'all' ? [] : ['filter' => $key]) }}" @if ($filter === $key) aria-current="page" @endif
                   class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $filter === $key ? 'bg-surface text-fg shadow-sm' : 'text-fg-secondary hover:text-fg' }}">{{ $label }}</a>
            @endforeach
        </nav>
        @if ($unreadCount)
            <form method="POST" action="{{ route('portal.notifications.read-all') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Mark all as read') }}</button>
            </form>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <x-ui.card>
            @include('portal._empty-module', [
                'title' => __('You’re all caught up.'),
                'description' => __('Updates about your projects, payments, documents and messages will show up here.'),
                'icon' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            ])
        </x-ui.card>
    @else
        @foreach (['today' => __('Today'), 'earlier' => __('Earlier')] as $group => $heading)
            @continue(! $groups->has($group))
            <section class="flex flex-col gap-2">
                <h2 class="text-xs font-semibold tracking-wide text-fg-tertiary uppercase">{{ $heading }}</h2>
                <x-ui.card padding="p-0">
                    <ul class="divide-y divide-line-subtle">
                        @foreach ($groups[$group] as $notification)
                            <li>
                                <form method="POST" action="{{ route('portal.notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-start gap-3 px-4 py-3 text-start text-sm hover:bg-subtle">
                                        <span class="flex-1 text-fg {{ $notification->read_at ? '' : 'font-semibold' }}" dir="auto">@include('portal._notification-text', ['notification' => $notification])</span>
                                        <span class="flex flex-col items-end gap-1">
                                            <span class="text-xs text-fg-tertiary">{{ $notification->created_at->isToday() ? $notification->created_at->format('H:i') : $notification->created_at->translatedFormat('j M') }}</span>
                                            @unless ($notification->read_at)
                                                <span class="size-2 rounded-full bg-brand" aria-hidden="true"></span><span class="sr-only">{{ __('Unread') }}</span>
                                            @endunless
                                        </span>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            </section>
        @endforeach

        {{ $notifications->links() }}
    @endif
</x-layouts.portal>
