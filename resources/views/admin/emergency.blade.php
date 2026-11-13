<x-layouts.admin :title="__('Emergency controls')" :crumb="__('AI & Automation')">
    <x-ui.alert tone="warning" :title="__('Changes take effect immediately')">
        {{ __('Every switch is enforced inside jobs, schedulers and MCP tools — not only in this screen. Each change is audited.') }}
    </x-ui.alert>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($switches as $switch)
            @include('admin._kill-switch-toggle', ['key' => $switch['key'], 'active' => $switch['active']])
        @endforeach
    </div>

    <x-ui.card :title="__('n8n workflows')">
        <p class="text-sm text-fg-secondary">{{ __('Enable, disable and inspect each signed n8n workflow (deliveries, retries and failed runs) on its own page.') }}</p>
        <x-ui.button variant="secondary" size="sm" class="mt-3" :href="route('admin.automation.index')">{{ __('n8n integrations') }}</x-ui.button>
    </x-ui.card>
</x-layouts.admin>
