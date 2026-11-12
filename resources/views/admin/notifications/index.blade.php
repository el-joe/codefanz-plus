<x-layouts.admin :title="__('Notifications')">
    <div class="flex justify-end">
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
            @csrf
            <x-ui.button variant="secondary" size="sm">{{ __('Mark all as read') }}</x-ui.button>
        </form>
    </div>

    <x-ui.card padding="p-0">
        @if ($notifications->isEmpty())
            <x-ui.empty-state :title="__('You’re all caught up.')" :description="__('New leads and assignments will show up here.')" />
        @else
            <ul class="divide-y divide-line-subtle">
                @foreach ($notifications as $notification)
                    <li>
                        <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 px-4 py-3 text-start text-sm hover:bg-subtle">
                                <span class="size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-brand' }}" aria-hidden="true"></span>
                                <span class="flex-1 text-fg {{ $notification->read_at ? '' : 'font-semibold' }}" dir="auto">@include('admin.notifications._text', ['notification' => $notification])</span>
                                @unless ($notification->read_at)
                                    <x-ui.badge tone="brand" :dot="false">{{ __('Unread') }}</x-ui.badge>
                                @endunless
                                <span class="text-xs text-fg-tertiary">{{ $notification->created_at->diffForHumans() }}</span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{ $notifications->links() }}
</x-layouts.admin>
