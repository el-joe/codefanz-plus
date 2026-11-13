{{-- Score ring: rule score, toned by the alert thresholds. --}}
@php
    $value = $score ?? null;
    $tone = match (true) {
        $value === null => 'border-line text-fg-tertiary',
        $value >= $thresholds['immediate'] => 'border-success text-fg-success bg-success-subtle',
        $value >= $thresholds['normal'] => 'border-brand text-fg-brand bg-brand-subtle',
        $value >= $thresholds['digest'] => 'border-warning text-fg-warning bg-warning-subtle',
        default => 'border-line-strong text-fg-secondary',
    };
@endphp
<span class="flex size-14 shrink-0 items-center justify-center rounded-full border-2 text-lg font-semibold {{ $tone }}" title="{{ __('Rule score') }}">
    <span class="sr-only">{{ __('Rule score') }}:</span>{{ $value ?? '—' }}
</span>
