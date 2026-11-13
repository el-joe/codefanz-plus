{{-- Accessible horizontal bar list: label, count, proportional bar. --}}
@php($max = max(1, ...array_values($rows ?: [0])))
@if ($rows === [] || array_sum($rows) === 0)
    <p class="text-sm text-fg-secondary">{{ __('No data in this period.') }}</p>
@else
    <ul class="flex flex-col gap-3">
        @foreach ($rows as $label => $count)
            <li class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-3 text-sm"><span class="text-fg" dir="auto">{{ $label }}</span><span class="font-semibold text-fg">{{ number_format($count) }}</span></div>
                <div class="h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true"><div class="h-full rounded-full bg-brand" style="width: {{ (int) round($count / $max * 100) }}%"></div></div>
            </li>
        @endforeach
    </ul>
@endif
