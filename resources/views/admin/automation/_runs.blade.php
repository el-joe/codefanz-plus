<table class="w-full text-sm">
    <thead class="bg-subtle text-xs text-fg-tertiary">
        <tr>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('When') }}</th>
            @if ($showWorkflow)<th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Workflow') }}</th>@endif
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Event / action') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Result') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Attempts') }}</th>
            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($runs as $run)
            <tr class="border-t border-line-subtle align-top" data-automation-run="{{ $run->id }}">
                <td class="px-4 py-3 text-fg-secondary">{{ $run->created_at->diffForHumans() }}<span class="block text-xs">{{ $run->direction->label() }}</span></td>
                @if ($showWorkflow)<td class="px-4 py-3 text-fg">{{ $run->workflow?->name ?? '—' }}</td>@endif
                <td class="px-4 py-3"><code class="font-mono text-xs text-fg" dir="ltr">{{ $run->event }}</code><span class="block font-mono text-[11px] text-fg-tertiary" dir="ltr">{{ \Illuminate\Support\Str::limit($run->idempotency_key, 40) }}</span></td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$run->status->tone()">{{ $run->status->label() }}</x-ui.badge>
                    @if ($run->http_status)<span class="ms-1 text-xs text-fg-tertiary" dir="ltr">HTTP {{ $run->http_status }}</span>@endif
                    @if ($run->error)<p class="mt-1 max-w-xs text-xs text-fg-danger" dir="ltr">{{ $run->error }}</p>@endif
                </td>
                <td class="px-4 py-3 text-fg-secondary">{{ $run->attempts }}@if ($run->duration_ms !== null)<span class="block text-xs">{{ $run->duration_ms }} ms</span>@endif</td>
                <td class="px-4 py-3 text-end">
                    @if ($run->direction === \App\Enums\AutomationDirection::Outbound && $run->status === \App\Enums\AutomationRunStatus::Failed)
                        <form method="POST" action="{{ route('admin.automation.runs.retry', $run) }}">
                            @csrf
                            <x-ui.button variant="secondary" size="sm">{{ __('Retry') }}</x-ui.button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
