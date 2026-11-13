{{-- Rule score breakdown: one bar per factor with its weight and the reason it scored that way. --}}
@php
    $factorLabels = [
        'skills' => __('Technical skills'),
        'budget' => __('Budget suitability'),
        'client' => __('Client quality'),
        'freshness' => __('Freshness'),
        'competition' => __('Competition'),
        'language' => __('Language'),
    ];
@endphp
<ul class="flex flex-col gap-4" data-score-breakdown>
    @foreach ((array) $evaluation?->breakdown as $factor)
        <li class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="font-semibold text-fg">{{ $factorLabels[$factor['factor']] ?? $factor['factor'] }}</span>
                <span class="text-xs text-fg-tertiary">{{ __('weight :weight', ['weight' => $factor['weight']]) }} · <span class="font-semibold text-fg">{{ $factor['score'] }}</span></span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                <div class="h-full rounded-full {{ $factor['score'] >= 75 ? 'bg-success' : ($factor['score'] >= 40 ? 'bg-brand' : 'bg-warning') }}" style="width: {{ max(2, (int) $factor['score']) }}%"></div>
            </div>
            <p class="text-xs text-fg-secondary" dir="auto">{{ __($factor['reason'], (array) ($factor['params'] ?? [])) }}</p>
        </li>
    @endforeach
</ul>
