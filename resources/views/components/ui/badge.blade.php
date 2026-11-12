@props(['tone' => 'neutral', 'dot' => true])

@php
    $tones = [
        'neutral' => 'bg-muted text-fg-secondary',
        'brand' => 'bg-brand-subtle text-fg-brand',
        'accent' => 'bg-accent-subtle text-fg-accent',
        'success' => 'bg-success-subtle text-fg-success',
        'warning' => 'bg-warning-subtle text-fg-warning',
        'danger' => 'bg-danger-subtle text-fg-danger',
        'info' => 'bg-info-subtle text-fg-info',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold '.($tones[$tone] ?? $tones['neutral'])]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
