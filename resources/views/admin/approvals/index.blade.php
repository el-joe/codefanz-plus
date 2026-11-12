<x-layouts.admin :title="__('Approval queue')" :crumb="__('AI & Automation')">
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Filter by status') }}">
        @foreach ([null, \App\Enums\ChangeRequestStatus::ChangesRequested, \App\Enums\ChangeRequestStatus::Approved, \App\Enums\ChangeRequestStatus::Applied, \App\Enums\ChangeRequestStatus::Rejected, \App\Enums\ChangeRequestStatus::Failed] as $status)
            @php($isActive = $activeStatus === $status)
            <a href="{{ route('admin.approvals.index', $status ? ['status' => $status->value] : []) }}"
               @if ($isActive) aria-current="page" @endif
               class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $isActive ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-subtle' }}">
                {{ $status ? __(\Illuminate\Support\Str::headline($status->value)) : __('Pending') }}
            </a>
        @endforeach
    </nav>

    <x-ui.card padding="p-0" class="overflow-hidden">
        @forelse ($changeRequests as $changeRequest)
            <a href="{{ route('admin.approvals.show', $changeRequest) }}" class="flex flex-wrap items-center gap-3 border-b border-line-subtle px-5 py-4 last:border-0 hover:bg-subtle">
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-fg">{{ $changeRequest->summary }}</span>
                    <span class="block text-xs text-fg-tertiary"><span class="font-mono">{{ $changeRequest->reference }}</span> · {{ $changeRequest->actor_label }} · {{ $changeRequest->created_at->diffForHumans() }}</span>
                </span>
                <x-ui.badge :tone="$changeRequest->origin === \App\Enums\ChangeOrigin::Mcp ? 'info' : 'accent'" :dot="false">{{ strtoupper($changeRequest->origin->value) }}</x-ui.badge>
                <x-change-request-status :status="$changeRequest->status" />
            </a>
        @empty
            <x-ui.empty-state :title="__('Nothing here')" :description="__('No change requests match this filter.')" />
        @endforelse
    </x-ui.card>

    {{ $changeRequests->links() }}
</x-layouts.admin>
