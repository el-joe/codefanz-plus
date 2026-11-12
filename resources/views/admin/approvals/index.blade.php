@php
    $tabLabels = ['pending' => __('Pending'), 'changes_requested' => __('Changes requested'), 'approved' => __('Approved'), 'applied' => __('Applied'), 'closed' => __('Rejected / failed')];
    $query = fn (array $override): array => array_filter([...['status' => $filters['tab'], 'origin' => $filters['origin']?->value, 'type' => $filters['type'], 'permission' => $filters['permission']?->value], ...$override]);
@endphp

<x-layouts.admin :title="__('Approval queue')" :crumb="__('AI & Automation')">
    <div class="flex flex-wrap items-center gap-3">
        <nav class="flex flex-wrap gap-1 rounded-lg bg-subtle p-1" aria-label="{{ __('Filter by status') }}">
            @foreach ($tabLabels as $key => $label)
                @php($isActive = $filters['tab'] === $key)
                <a href="{{ route('admin.approvals.index', $query(['status' => $key])) }}" @if ($isActive) aria-current="page" @endif
                   class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $isActive ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-surface' }}">
                    {{ $label }} <span class="text-fg-tertiary">({{ $tabs[$key] }})</span>
                </a>
            @endforeach
        </nav>
        <span class="ms-auto">
            @if ($killSwitchesOff)
                <x-ui.badge tone="success">{{ __('Kill switches: all off') }}</x-ui.badge>
            @else
                <x-ui.badge tone="danger">{{ __('An emergency control is active') }}</x-ui.badge>
            @endif
        </span>
        @can('automation.manage')
            <a href="{{ route('admin.emergency.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Emergency controls') }}</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('admin.approvals.index') }}" data-auto-submit class="flex flex-wrap items-end gap-3" data-approval-filters>
        <input type="hidden" name="status" value="{{ $filters['tab'] }}">
        <div class="w-44"><x-ui.select name="origin" :label="__('Origin')" :options="['ai' => __('AI'), 'mcp' => __('MCP'), 'human' => __('Human')]" :value="$filters['origin']?->value" :placeholder="__('All origins')" /></div>
        <div class="w-52"><x-ui.select name="type" :label="__('Content type')" :options="$targetTypes->mapWithKeys(fn ($type) => [$type => __(\Illuminate\Support\Str::headline($type))])->all()" :value="$filters['type']" :placeholder="__('All types')" /></div>
        <div class="w-52"><x-ui.select name="permission" :label="__('Required permission')" :options="collect(\App\Enums\Permission::approvalPermissions())->mapWithKeys(fn ($permission) => [$permission->value => $permission->value])->all()" :value="$filters['permission']?->value" :placeholder="__('Any permission')" /></div>
        <noscript><x-ui.button size="sm" variant="secondary">{{ __('Filter') }}</x-ui.button></noscript>
        @if ($filters['origin'] || $filters['type'] || $filters['permission'])
            <a href="{{ route('admin.approvals.index', ['status' => $filters['tab']]) }}" class="pb-2.5 text-sm font-semibold text-fg-brand hover:underline">{{ __('Clear filters') }}</a>
        @endif
    </form>

    <form method="POST" action="{{ route('admin.approvals.bulk') }}" data-confirm="{{ __('Approve the selected requests?') }}" data-confirm-label="{{ __('Approve') }}">
        @csrf
        <x-ui.card padding="p-0" class="overflow-hidden">
            @forelse ($changeRequests as $changeRequest)
                @php($bulkable = \App\Http\Controllers\Admin\ChangeRequestController::bulkApprovable($changeRequest) && auth()->user()->can('review', $changeRequest))
                <div class="flex flex-wrap items-center gap-3 border-b border-line-subtle px-5 py-4 last:border-0 hover:bg-subtle" data-change-request="{{ $changeRequest->reference }}">
                    @if ($filters['tab'] === 'pending')
                        <input type="checkbox" name="ids[]" value="{{ $changeRequest->id }}" @disabled(! $bulkable) aria-label="{{ __('Select :reference', ['reference' => $changeRequest->reference]) }}"
                               @unless ($bulkable) title="{{ __('Needs an individual review') }}" data-bulk-disabled @endunless class="size-4 rounded border-line-strong disabled:opacity-40">
                    @endif
                    <a href="{{ route('admin.approvals.show', $changeRequest) }}" class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-fg-tertiary">{{ $changeRequest->reference }}</span>
                            <x-ui.badge :tone="$changeRequest->origin === \App\Enums\ChangeOrigin::Mcp ? 'info' : ($changeRequest->origin === \App\Enums\ChangeOrigin::Ai ? 'accent' : 'neutral')" :dot="false">{{ strtoupper($changeRequest->origin->value) }}</x-ui.badge>
                            <x-ui.badge :dot="false">{{ $changeRequest->action->value }}</x-ui.badge>
                            @if ($changeRequest->hasGuardrailFlags())
                                <x-ui.badge tone="warning" :dot="false" data-flagged>{{ __('Flags: :count', ['count' => count($changeRequest->guardrail_flags)]) }}</x-ui.badge>
                            @endif
                        </span>
                        <span class="mt-1 block text-sm font-semibold text-fg" dir="auto">{{ $changeRequest->summary }}</span>
                        <span class="block text-xs text-fg-tertiary" dir="auto">
                            {{ $changeRequest->actor_label ?? __('System') }} · {{ $changeRequest->required_permission->value }} · {{ $changeRequest->created_at->diffForHumans() }}
                            @if ($changeRequest->expires_at && $changeRequest->status->isReviewable())
                                · <span class="{{ $changeRequest->isExpired() ? 'text-fg-danger' : '' }}">{{ $changeRequest->isExpired() ? __('Expired') : __('Expires :when', ['when' => $changeRequest->expires_at->diffForHumans()]) }}</span>
                            @endif
                        </span>
                    </a>
                    <x-change-request-status :status="$changeRequest->status" />
                </div>
            @empty
                <x-ui.empty-state :title="__('Nothing here')" :description="__('No change requests match this filter.')" />
            @endforelse
        </x-ui.card>

        @if ($filters['tab'] === 'pending' && $changeRequests->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-ui.button variant="secondary" size="sm">{{ __('Approve selected') }}</x-ui.button>
                <span class="text-xs text-fg-tertiary">{{ __('AI items with guardrail flags, your own proposals and items needing another permission can’t be bulk-approved.') }}</span>
            </div>
        @endif
    </form>

    {{ $changeRequests->links() }}
</x-layouts.admin>
