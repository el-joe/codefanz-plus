<x-layouts.admin :title="__('Overview')">
    <div>
        <h2 class="text-2xl font-bold text-fg">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h2>
        <p class="text-sm text-fg-secondary">{{ trans_choice(':count change request is waiting for review.|:count change requests are waiting for review.', $pendingCount) }}</p>
    </div>

    @if ($activeKillSwitches->isNotEmpty())
        <x-ui.alert tone="danger" :title="__('Emergency controls are active')">
            {{ $activeKillSwitches->map(fn ($key) => __(\Illuminate\Support\Str::headline($key->value)))->implode(' · ') }}
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <x-ui.card :title="__('Approval queue')">
            @can('viewAny', \App\Models\ChangeRequest::class)
                <x-slot:action><a href="{{ route('admin.approvals.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Review queue') }}</a></x-slot:action>
            @endcan
            @forelse ($pendingApprovals as $changeRequest)
                <a href="{{ route('admin.approvals.show', $changeRequest) }}" class="flex items-center gap-3 border-b border-line-subtle py-3 last:border-0">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-fg">{{ $changeRequest->summary }}</span>
                        <span class="block font-mono text-xs text-fg-tertiary">{{ $changeRequest->reference }} · {{ $changeRequest->actor_label }}</span>
                    </span>
                    <x-ui.badge :tone="$changeRequest->origin === \App\Enums\ChangeOrigin::Mcp ? 'info' : 'accent'" :dot="false">{{ strtoupper($changeRequest->origin->value) }}</x-ui.badge>
                </a>
            @empty
                <x-ui.empty-state :title="__('Nothing to review')" :description="__('AI and MCP proposals will appear here before anything changes on the live site.')" />
            @endforelse
        </x-ui.card>

        <x-ui.card :title="__('Recent activity')">
            @forelse ($recentActivity as $log)
                <div class="flex items-center gap-3 border-b border-line-subtle py-3 last:border-0">
                    <span class="min-w-0 flex-1">
                        <span class="block font-mono text-xs text-fg">{{ $log->operation }}</span>
                        <span class="block text-xs text-fg-tertiary">{{ $log->actor_label ?? __('System') }} · {{ $log->created_at->diffForHumans() }}</span>
                    </span>
                    <x-ui.badge :tone="$log->result === 'success' ? 'success' : 'danger'" :dot="false">{{ __(ucfirst($log->result)) }}</x-ui.badge>
                </div>
            @empty
                <x-ui.empty-state :title="__('No activity yet')" />
            @endforelse
        </x-ui.card>
    </div>
</x-layouts.admin>
