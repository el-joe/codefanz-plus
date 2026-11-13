{{-- Honest connector status per platform (B09). --}}
<div class="flex flex-wrap items-center gap-2 text-xs" data-radar-connectors>
    <span class="font-semibold text-fg-tertiary">{{ __('Connectors:') }}</span>
    @foreach ($platforms as $platform)
        @php($status = $platform->displayStatus())
        <x-ui.badge :tone="$status->tone()" title="{{ __($platform->status_note ?? '') }}" data-platform="{{ $platform->key }}">
            {{ $platform->name }} · {{ $platform->connector->label() }} · {{ $status->label() }}
        </x-ui.badge>
    @endforeach
</div>
