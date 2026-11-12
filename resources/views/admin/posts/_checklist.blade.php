{{-- Automatic editorial checks with the overall score. Re-rendered live by the editor (POST admin/posts/checklist). --}}
@php
    $score = \App\Services\Blog\EditorialChecklist::score($checklist);
    $scoreTone = $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
@endphp
<div class="flex flex-col gap-3">
    <p class="flex items-center justify-between gap-2 text-sm text-fg-secondary">
        {{ __('Checklist score') }}
        <x-ui.badge :tone="$scoreTone" :dot="false" data-checklist-score>{{ $score }}/100</x-ui.badge>
    </p>
    <ul class="flex flex-col gap-2.5 text-sm">
        @foreach (collect($checklist)->where('manual', false) as $item)
            <li class="flex items-start gap-2" data-check="{{ $item['key'] }}" data-passed="{{ $item['passed'] ? 'true' : 'false' }}">
                @if ($item['passed'])
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-0.5 shrink-0 text-fg-success" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                @elseif ($item['required'])
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-0.5 shrink-0 text-fg-danger" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>
                @else
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mt-0.5 shrink-0 text-fg-warning" aria-hidden="true"><path d="M12 3 2 21h20L12 3z"/><path d="M12 10v4m0 3h.01"/></svg>
                @endif
                <span class="flex flex-col">
                    <span class="{{ $item['passed'] ? 'text-fg' : ($item['required'] ? 'font-semibold text-fg-danger' : 'text-fg-warning') }}">
                        {{ $item['label'] }}
                        <span class="sr-only">— {{ $item['passed'] ? __('passed') : ($item['required'] ? __('required') : __('warning')) }}</span>
                    </span>
                    @if ($item['detail'])
                        <span class="text-xs text-fg-tertiary">{{ $item['detail'] }}</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
</div>
