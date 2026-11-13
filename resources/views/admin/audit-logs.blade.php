<x-layouts.admin :title="__('Audit logs')" :crumb="__('System')">
    <form method="GET" data-auto-submit class="flex items-end gap-3">
        <div class="flex flex-col gap-1.5">
            <label for="actor" class="text-sm font-semibold text-fg">{{ __('Actor type') }}</label>
            <select id="actor" name="actor" class="rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg">
                <option value="">{{ __('All actors') }}</option>
                @foreach (\App\Enums\ActorType::cases() as $type)
                    <option value="{{ $type->value }}" @selected($actorType === $type)>{{ __(\Illuminate\Support\Str::headline($type->value)) }}</option>
                @endforeach
            </select>
        </div>
        <noscript><x-ui.button size="sm" variant="secondary">{{ __('Filter') }}</x-ui.button></noscript>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-start text-xs text-fg-tertiary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Time') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Actor') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Operation') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Target') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Result') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-t border-line-subtle">
                        <td class="whitespace-nowrap px-4 py-3 text-fg-secondary">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3"><span class="block text-fg">{{ $log->actor_label ?? __('System') }}</span><span class="text-xs text-fg-tertiary">{{ __(\Illuminate\Support\Str::headline($log->actor_type->value)) }}</span></td>
                        <td class="px-4 py-3 font-mono text-xs text-fg">{{ $log->operation }}</td>
                        <td class="px-4 py-3 text-fg-secondary">{{ $log->auditable_type ? $log->auditable_type.' #'.$log->auditable_id : '—' }}</td>
                        <td class="px-4 py-3"><x-ui.badge :tone="$log->result === 'success' ? 'success' : 'danger'">{{ __(ucfirst($log->result)) }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state :title="__('No audit entries yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    {{ $logs->links() }}
</x-layouts.admin>
