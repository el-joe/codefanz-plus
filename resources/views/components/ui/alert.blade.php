@props(['tone' => 'info', 'title' => null])

@php
    $tones = [
        'info' => 'border-line-info bg-info-subtle',
        'success' => 'border-line-success bg-success-subtle',
        'warning' => 'border-line-warning bg-warning-subtle',
        'danger' => 'border-line-danger bg-danger-subtle',
    ];
@endphp

<div role="{{ in_array($tone, ['warning', 'danger'], true) ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => 'rounded-lg border p-4 '.($tones[$tone] ?? $tones['info'])]) }}>
    @if ($title)
        <p class="text-sm font-semibold text-fg">{{ $title }}</p>
    @endif
    <div class="text-sm text-fg-secondary">{{ $slot }}</div>
</div>
