<x-layouts.admin :title="__('Emergency controls')" :crumb="__('AI & Automation')">
    <x-ui.alert tone="warning" :title="__('Changes take effect immediately')">
        {{ __('Every switch is enforced inside jobs, schedulers and MCP tools — not only in this screen. Each change is audited.') }}
    </x-ui.alert>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($switches as $switch)
            <x-ui.card>
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-base font-semibold text-fg">{{ __(\Illuminate\Support\Str::headline($switch['key']->value)) }}</h2>
                    <x-ui.badge :tone="$switch['active'] ? 'danger' : 'success'">{{ $switch['active'] ? __('Paused') : __('Running') }}</x-ui.badge>
                </div>
                <form method="POST" action="{{ route('admin.emergency.update', $switch['key']->value) }}" class="mt-4 flex flex-col gap-3"
                      @unless ($switch['active']) data-confirm="{{ __('Pause this immediately for everyone?') }}" data-confirm-label="{{ __('Pause') }}" @endunless>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="active" value="{{ $switch['active'] ? 0 : 1 }}">
                    <label class="sr-only" for="reason-{{ $switch['key']->value }}">{{ __('Reason') }}</label>
                    <input id="reason-{{ $switch['key']->value }}" name="reason" maxlength="255" placeholder="{{ __('Reason (optional)') }}" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">
                    <x-ui.button :variant="$switch['active'] ? 'secondary' : 'danger'" size="sm">{{ $switch['active'] ? __('Resume') : __('Pause now') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.admin>
