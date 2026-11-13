{{-- MCP tool-call log rows (sanitized arguments in a details toggle). --}}
<table class="w-full text-sm">
    <thead class="bg-subtle text-xs text-fg-tertiary">
        <tr>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Time') }}</th>
            @if ($showClient)
                <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Client') }}</th>
            @endif
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Tool') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Target / request') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Duration') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Result') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($calls as $call)
            <tr class="border-t border-line-subtle align-top">
                <td class="px-4 py-3 whitespace-nowrap text-fg-secondary"><time datetime="{{ $call->created_at->toIso8601String() }}" title="{{ $call->created_at->toDayDateTimeString() }}">{{ $call->created_at->diffForHumans() }}</time></td>
                @if ($showClient)
                    <td class="px-4 py-3 font-mono text-xs text-fg" dir="ltr">{{ $call->client?->name ?? '—' }}</td>
                @endif
                <td class="px-4 py-3 font-mono text-xs text-fg" dir="ltr">{{ $call->tool }}</td>
                <td class="px-4 py-3 text-fg-secondary">
                    @if ($call->changeRequest)
                        <a href="{{ route('admin.approvals.show', $call->changeRequest) }}" class="font-semibold text-fg-brand hover:underline" dir="ltr">{{ $call->changeRequest->reference }}</a>
                    @endif
                    @if ($call->arguments)
                        <details class="mt-1">
                            <summary class="cursor-pointer text-xs text-fg-tertiary">{{ __('Arguments') }}</summary>
                            <pre class="mt-1 max-w-md overflow-x-auto rounded bg-subtle p-2 text-xs text-fg" dir="ltr">{{ json_encode($call->arguments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @endif
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-fg-secondary" dir="ltr">{{ $call->duration_ms }} ms</td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$call->result->tone()">{{ $call->result->label() }}</x-ui.badge>
                    @if ($call->error_code)
                        <span class="mt-1 block font-mono text-xs text-fg-tertiary" dir="ltr">{{ $call->error_code }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
