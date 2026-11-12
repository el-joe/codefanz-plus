@php($usd = fn (float $minor): string => '$'.number_format($minor / 100, 2))

<x-layouts.admin :title="__('AI governance')" :crumb="__('AI & Automation')">
    <x-ui.alert tone="info" :title="__('Every switch is enforced in the backend')">
        {{ __('Pausing a feature stops new AI calls inside the gateway, jobs and MCP tools — not only in the screens. Every change is audited. Pending approvals are never applied while AI automation is paused.') }}
    </x-ui.alert>

    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card>
            <p class="text-sm text-fg-tertiary">{{ __('Provider') }}</p>
            <p class="mt-1 text-lg font-bold text-fg" dir="ltr">{{ $provider->isConfigured() ? $provider->name().' · '.$provider->model() : __('Not configured') }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-sm text-fg-tertiary">{{ __('Budget this month (hard stop)') }}</p>
            <p class="mt-1 text-lg font-bold text-fg" dir="ltr">{{ $usd($spentMinor) }} / {{ $usd($limitMinor) }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-sm text-fg-tertiary">{{ __('AI and MCP proposals awaiting review') }}</p>
            <p class="mt-1 text-lg font-bold text-fg"><a href="{{ route('admin.approvals.index') }}" class="hover:text-fg-brand">{{ number_format($pendingAi) }}</a></p>
        </x-ui.card>
    </div>

    <x-ui.card :title="__('Master controls')">
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($global as $switch)
                @include('admin._kill-switch-toggle', ['key' => $switch['key'], 'active' => $switch['active']])
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <h2 class="px-5 pt-5 text-lg font-semibold text-fg">{{ __('AI features') }}</h2>
        <table class="mt-3 w-full text-sm" data-ai-features>
            <thead class="bg-subtle text-xs text-fg-tertiary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Feature') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Tokens this month') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Monthly cap') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($features as $row)
                    <tr class="border-t border-line-subtle" data-feature="{{ $row['feature']->value }}">
                        <td class="px-4 py-3">
                            <span class="font-semibold text-fg">{{ $row['feature']->label() }}</span>
                            <span class="block text-xs text-fg-tertiary">{{ $row['switch']->description() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($row['paused'])
                                <x-ui.badge tone="danger">{{ __('Paused') }}</x-ui.badge>
                            @elseif ($row['reason'])
                                <x-ui.badge tone="warning">{{ $row['reason'] === \App\Enums\AiUnavailableReason::Disabled ? __('Paused by AI automation') : \Illuminate\Support\Str::limit($row['reason']->label(), 40) }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="success">{{ __('Running') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-end text-fg" dir="ltr">{{ number_format($row['tokens']) }}</td>
                        <td class="px-4 py-3 text-end text-fg-secondary" dir="ltr">{{ $row['cap'] === null ? __('No cap') : number_format($row['cap']) }}</td>
                        <td class="px-4 py-3 text-end">
                            <form method="POST" action="{{ route('admin.emergency.update', $row['switch']->value) }}" @unless ($row['paused']) data-confirm="{{ __('Pause this immediately for everyone?') }}" data-confirm-label="{{ __('Pause') }}" @endunless>
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="active" value="{{ $row['paused'] ? 0 : 1 }}">
                                <x-ui.button :variant="$row['paused'] ? 'secondary' : 'danger'" size="sm">{{ $row['paused'] ? __('Resume') : __('Pause now') }}</x-ui.button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card :title="__('Usage limits')">
        <dl class="grid gap-3 text-sm md:grid-cols-2">
            <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Requests per staff member per day') }}</dt><dd class="text-fg" dir="ltr">{{ $userDailyRequests === null ? __('No cap') : number_format($userDailyRequests) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-fg-secondary">{{ __('Tokens per staff member per month') }}</dt><dd class="text-fg" dir="ltr">{{ $userMonthlyTokens === null ? __('No cap') : number_format($userMonthlyTokens) }}</dd></div>
        </dl>
        @can('settings.manage')
            <a href="{{ route('admin.settings.edit', 'ai') }}" class="mt-4 inline-block text-sm font-semibold text-fg-brand hover:underline">{{ __('Change limits in Settings → AI limits') }}</a>
        @endcan
    </x-ui.card>
</x-layouts.admin>
