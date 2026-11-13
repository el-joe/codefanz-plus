<x-layouts.admin :title="__('n8n integrations')" :crumb="__('AI & Automation')">
    @if ($paused)
        <x-ui.alert tone="danger" :title="__('n8n webhooks are paused')">{{ __('The emergency control is on: deliveries fail without being sent and inbound requests are refused.') }} <a href="{{ route('admin.emergency.index') }}" class="font-semibold underline">{{ __('Emergency controls') }}</a></x-ui.alert>
    @endif

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-4">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-fg">{{ __('n8n workflows') }}</h2>
                <x-ui.badge tone="success" :dot="false">{{ __('Signed webhooks · HMAC-SHA256') }}</x-ui.badge>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button size="sm" :href="route('admin.automation.create', ['direction' => 'outbound'])">{{ __('New outbound workflow') }}</x-ui.button>
                <x-ui.button size="sm" variant="secondary" :href="route('admin.automation.create', ['direction' => 'inbound'])">{{ __('New inbound endpoint') }}</x-ui.button>
            </div>
        </div>
        @if ($workflows->isEmpty())
            <x-ui.empty-state :title="__('No workflows yet')" :description="__('Outbound workflows send signed events (new lead, payment verified, radar match…) to an n8n webhook. Inbound endpoints let n8n create leads, add lead notes, open tickets or import radar alert emails.')" />
        @else
            <ul>
                @foreach ($workflows as $workflow)
                    <li class="flex flex-wrap items-center gap-3 border-t border-line-subtle px-5 py-3" data-workflow="{{ $workflow->key }}">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.automation.edit', $workflow) }}" class="text-sm font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $workflow->name }}</a>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <x-ui.badge :dot="false">{{ $workflow->direction->label() }}</x-ui.badge>
                                <code class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $workflow->isOutbound() ? implode(', ', (array) $workflow->events) : $workflow->action?->value }}</code>
                            </div>
                        </div>
                        <span class="text-sm text-fg-secondary">{{ $workflow->last_run_at?->diffForHumans() ?? '—' }}</span>
                        @if (! $workflow->is_enabled)
                            <x-ui.badge>{{ __('Disabled') }}</x-ui.badge>
                        @elseif ($workflow->last_status)
                            <x-ui.badge :tone="$workflow->last_status->tone()">{{ $workflow->last_status === \App\Enums\AutomationRunStatus::Succeeded ? __('Healthy') : $workflow->last_status->label() }}</x-ui.badge>
                        @else
                            <x-ui.badge tone="info">{{ __('No runs yet') }}</x-ui.badge>
                        @endif
                        <form method="POST" action="{{ route('admin.automation.toggle', $workflow) }}"
                              @if ($workflow->is_enabled) data-confirm="{{ __('Disable :name?', ['name' => $workflow->name]) }}" data-confirm-label="{{ __('Disable') }}" @endif>
                            @csrf
                            <button type="submit" role="switch" aria-checked="{{ $workflow->is_enabled ? 'true' : 'false' }}" aria-label="{{ __('Enable :name', ['name' => $workflow->name]) }}"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $workflow->is_enabled ? 'bg-brand' : 'bg-muted' }}">
                                <span class="inline-block size-5 rounded-full bg-surface shadow transition-transform {{ $workflow->is_enabled ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5' }}"></span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.alert tone="info" :title="__('Laravel stays the source of truth')">{{ __('n8n handles external workflows with retries and idempotency keys (the X-CodeFanz-Delivery header). Financial status never depends on an unverified n8n run, and inbound endpoints can only run their one whitelisted action.') }}</x-ui.alert>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <div class="flex flex-col gap-4 px-5 pt-5 pb-4">
            <h2 class="text-base font-semibold text-fg">{{ __('Run log') }}</h2>
            <form method="GET" class="flex flex-wrap items-end gap-3" data-auto-submit>
                <div class="w-56"><x-ui.select name="workflow" :label="__('Workflow')" :options="$workflows->pluck('name', 'id')->all()" :value="$filters['workflow'] ?? null" :placeholder="__('All workflows')" /></div>
                <div class="w-44"><x-ui.select name="status" :label="__('Result')" :options="\App\Enums\AutomationRunStatus::options()" :value="$filters['status'] ?? null" :placeholder="__('All results')" /></div>
                <x-ui.button variant="secondary" size="sm">{{ __('Filter') }}</x-ui.button>
            </form>
        </div>
        @if ($runs->isEmpty())
            <x-ui.empty-state :title="__('No runs yet')" :description="__('Every delivery and every accepted inbound request is listed here.')" />
        @else
            @include('admin.automation._runs', ['runs' => $runs, 'showWorkflow' => true])
        @endif
    </x-ui.card>

    {{ $runs->links() }}
</x-layouts.admin>
