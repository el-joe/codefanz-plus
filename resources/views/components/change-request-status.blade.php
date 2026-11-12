@props(['status'])

<x-ui.badge :tone="$status->tone()">{{ __(\Illuminate\Support\Str::headline($status->value)) }}</x-ui.badge>
