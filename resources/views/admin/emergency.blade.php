<x-layouts.admin :title="__('Emergency controls')" :crumb="__('AI & Automation')">
    <x-ui.alert tone="warning" :title="__('Changes take effect immediately')">
        {{ __('Every switch is enforced inside jobs, schedulers and MCP tools — not only in this screen. Each change is audited.') }}
    </x-ui.alert>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($switches as $switch)
            @include('admin._kill-switch-toggle', ['key' => $switch['key'], 'active' => $switch['active']])
        @endforeach
    </div>
</x-layouts.admin>
