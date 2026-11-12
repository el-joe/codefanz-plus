<a href="{{ route('admin.ai-studio.generations.show', $generation) }}" class="flex flex-wrap items-center gap-3 border-b border-line-subtle px-5 py-3 last:border-0 hover:bg-subtle" data-generation-row>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold text-fg" dir="auto">{{ $generation->headline() ?? ($generation->template ? __($generation->template->name) : $generation->purpose->label()) }}</span>
        <span class="block text-xs text-fg-tertiary" dir="auto">
            {{ $generation->purpose->label() }} · {{ collect($generation->locales)->map(fn ($code) => strtoupper($code))->implode(' + ') }}
            @if (isset($showUser) && $generation->user) · {{ $generation->user->name }} @endif
            · {{ $generation->created_at->diffForHumans() }}
        </span>
    </span>
    @if ($generation->hasFlags())
        <x-ui.badge tone="warning" :dot="false">{{ __('Flags: :count', ['count' => count($generation->guardrail_flags)]) }}</x-ui.badge>
    @endif
    @if ($generation->is_dry_run)
        <x-ui.badge :dot="false">{{ __('Test run') }}</x-ui.badge>
    @endif
    <span class="font-mono text-xs text-fg-secondary" dir="ltr">${{ number_format($generation->estimated_cost_minor / 100, 2) }}</span>
    <x-ui.badge :tone="$generation->status->tone()">{{ $generation->status->label() }}</x-ui.badge>
</a>
