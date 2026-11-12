@php
    $usd = function (float $minor): string {
        if ($minor > 0 && $minor < 1) {
            return '< $0.01';
        }

        return '$'.number_format($minor / 100, 2);
    };
    $budgetTone = $percent >= 100 ? 'danger' : ($percent >= 80 ? 'warning' : 'success');
@endphp

<x-layouts.admin :title="__('AI usage')" :crumb="__('AI & Automation')">
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card>
            <p class="text-sm text-fg-tertiary">{{ __('Budget this month') }}</p>
            <p class="mt-1 text-2xl font-bold text-fg" dir="ltr" data-ai-budget>{{ $usd($spentMinor) }} / {{ '$'.number_format($limitMinor / 100, 2) }}</p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ __('Budget used') }}">
                <div class="h-full rounded-full {{ $budgetTone === 'danger' ? 'bg-danger' : ($budgetTone === 'warning' ? 'bg-fg-warning' : 'bg-fg-success') }}" style="width: {{ $percent }}%"></div>
            </div>
            <div class="mt-3">
                @if ($percent >= 100)
                    <x-ui.badge tone="danger" data-ai-budget-status="exceeded">{{ __('Budget reached — AI calls are stopped until next month or a higher budget') }}</x-ui.badge>
                @elseif ($percent >= 80)
                    <x-ui.badge tone="warning" data-ai-budget-status="near">{{ __(':percent% of the budget used', ['percent' => $percent]) }}</x-ui.badge>
                @else
                    <x-ui.badge tone="success" data-ai-budget-status="ok">{{ __(':percent% of the budget used', ['percent' => $percent]) }}</x-ui.badge>
                @endif
            </div>
        </x-ui.card>
        <x-ui.card>
            <p class="text-sm text-fg-tertiary">{{ __('Provider') }}</p>
            <p class="mt-1 text-lg font-bold text-fg" dir="ltr">{{ $provider->isConfigured() ? $provider->name().' · '.$provider->model() : __('Not configured') }}</p>
            <p class="mt-2 text-xs text-fg-tertiary">{{ __('Change the provider, model, keys and budget in Settings → Integrations.') }}</p>
            <a href="{{ route('admin.settings.edit', 'integrations') }}" class="mt-2 inline-block text-sm font-semibold text-fg-brand hover:underline">{{ __('Open integrations') }}</a>
        </x-ui.card>
        <x-ui.card>
            <p class="text-sm text-fg-tertiary">{{ __('AI Project Assistant') }}</p>
            <div class="mt-2">
                @if ($assistantReason === null)
                    <x-ui.badge tone="success">{{ __('AI conversation') }}</x-ui.badge>
                @else
                    <x-ui.badge tone="warning">{{ __('Guided questionnaire') }} · {{ $assistantReason->label() }}</x-ui.badge>
                @endif
            </div>
            <p class="mt-2 text-xs text-fg-tertiary">{{ __('When AI is unavailable, visitors get a guided questionnaire and their briefs still become leads.') }}</p>
        </x-ui.card>
    </div>

    <form method="GET" data-auto-submit class="flex items-end gap-3">
        <div class="flex flex-col gap-1.5">
            <label for="month" class="text-sm font-semibold text-fg">{{ __('Month') }}</label>
            <select id="month" name="month" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">
                @foreach ($months as $option)
                    <option value="{{ $option->format('Y-m') }}" @selected($option->isSameMonth($month))>{{ $option->translatedFormat('F Y') }}</option>
                @endforeach
            </select>
        </div>
        <noscript><x-ui.button size="sm" variant="secondary">{{ __('Filter') }}</x-ui.button></noscript>
        <p class="pb-2 text-sm text-fg-secondary">{{ __('Estimated cost for :month: :cost', ['month' => $month->translatedFormat('F Y'), 'cost' => $usd($monthCost)]) }}</p>
    </form>

    @foreach ([__('By feature') => $byFeature, __('By provider and model') => $byModel] as $heading => $rows)
        <x-ui.card padding="p-0" class="overflow-x-auto">
            <h2 class="px-4 pt-4 text-base font-semibold text-fg">{{ $heading }}</h2>
            <table class="mt-3 w-full text-sm" data-ai-usage-table>
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Calls') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Failed') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Input tokens') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Output tokens') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Estimated cost') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3 text-fg" dir="auto">{{ $row['label'] }}</td>
                            <td class="px-4 py-3 text-end text-fg" dir="ltr">{{ number_format($row['calls']) }}</td>
                            <td class="px-4 py-3 text-end text-fg-secondary" dir="ltr">{{ number_format($row['failed']) }}</td>
                            <td class="px-4 py-3 text-end text-fg" dir="ltr">{{ number_format($row['input_tokens']) }}</td>
                            <td class="px-4 py-3 text-end text-fg" dir="ltr">{{ number_format($row['output_tokens']) }}</td>
                            <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ $usd($row['cost_minor']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state :title="__('No AI calls this month')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    @endforeach

    @if ($failures->isNotEmpty())
        <x-ui.card :title="__('Recent failures')">
            <ul class="flex flex-col divide-y divide-line-subtle text-sm">
                @foreach ($failures as $failure)
                    <li class="flex flex-wrap gap-x-3 py-2">
                        <span class="text-fg-tertiary" dir="ltr">{{ $failure->created_at?->format('Y-m-d H:i') }}</span>
                        <span class="text-fg">{{ $failure->feature->label() }}</span>
                        <span class="text-fg-secondary" dir="ltr">{{ $failure->provider }} · {{ $failure->model }}</span>
                        <span class="text-fg-danger" dir="ltr">{{ $failure->error }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <p class="text-xs text-fg-tertiary">{{ __('Costs are estimates from the price table in config/codefanz.php; check them against your provider’s invoice. Prompts and replies are never stored in the usage log.') }}</p>
</x-layouts.admin>
