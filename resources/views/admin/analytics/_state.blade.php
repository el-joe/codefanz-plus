{{-- Honest connection state for one Google source. Expects $source (ga4 or gsc data array) and $name. --}}
@php
    $state = $source['state'];
    $fetched = $source['fetchedAt'];
@endphp
@switch ($state)
    @case('connected')
        <x-ui.badge tone="success" data-state="connected">{{ __(':name connected', ['name' => $name]) }}</x-ui.badge>
        @break
    @case('pending')
        <x-ui.badge tone="info" data-state="pending">{{ __('Credentials saved · not synced yet') }}</x-ui.badge>
        @break
    @case('reconnect')
        <x-ui.badge tone="danger" data-state="reconnect">{{ __('Access expired or denied') }}</x-ui.badge>
        @break
    @case('error')
        <x-ui.badge tone="warning" data-state="error">{{ __('Last sync failed') }}</x-ui.badge>
        @break
    @default
        <x-ui.badge data-state="not_configured">{{ __('Requires credentials') }}</x-ui.badge>
@endswitch

@if (in_array($state, ['reconnect', 'error', 'not_configured', 'pending'], true))
    <x-ui.alert :tone="$state === 'reconnect' ? 'danger' : ($state === 'error' ? 'warning' : 'info')"
                :title="match ($state) { 'reconnect' => __('Reconnect Google to resume the daily sync'), 'error' => __('Google could not be reached on the last sync'), 'pending' => __('Waiting for the first sync'), default => __(':name requires credentials', ['name' => $name]) }">
        @if ($state === 'not_configured')
            {{ __('Save a Google service account key and the property in Settings → Integrations, then grant that service account read access in Google. Until then no numbers are shown.') }}
        @elseif ($state === 'pending')
            {{ __('The daily sync runs at 06:00, or use “Refresh now”.') }}
        @else
            {{ $source['status']['message'] ?? '' }}
        @endif
        @if ($fetched)
            {{ __('Showing cached data from :date — nothing is invented while disconnected.', ['date' => $fetched->translatedFormat('j M Y, H:i')]) }}
        @endif
        @can('settings.manage')
            <a href="{{ route('admin.settings.edit', 'integrations') }}" class="font-semibold underline">{{ $state === 'reconnect' ? __('Reconnect Google') : __('Open Settings → Integrations') }}</a>
        @endcan
    </x-ui.alert>
@endif
