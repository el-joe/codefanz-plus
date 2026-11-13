{{-- Links to the other active languages (keeps the current page via `alternates`). `globe` renders the Figma navbar style: globe icon + native name, icon only below `sm`. --}}
@props(['alternates' => [], 'globe' => false])

@php
    $languages = \App\Models\Language::active();
    $current = app()->getLocale();
    $route = request()->route();
    $isLocalized = $route && in_array('locale.route', $route->gatherMiddleware(), true);
    $itemClass = $globe
        ? 'inline-flex min-h-10 min-w-10 items-center justify-center gap-1.5 rounded-md px-2.5 py-2 type-label-md text-fg hover:bg-subtle'
        : 'rounded-md px-2.5 py-1.5 text-sm font-semibold text-fg hover:bg-subtle';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1']) }} role="group" aria-label="{{ __('Language') }}">
    @foreach ($languages as $code => $language)
        @continue($code === $current)
        @php($label = $globe ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-icon-primary"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg><span class="max-sm:sr-only'.($code === 'ar' ? ' font-arabic' : ' font-sans').'">'.e($language['native_name']).'</span>' : e($language['native_name']))
        @if ($isLocalized)
            <a href="{{ $alternates[$code] ?? route('home', ['locale' => $code]) }}"
               hreflang="{{ $code }}" lang="{{ $code }}" class="{{ $itemClass }}">{!! $label !!}</a>
        @else
            <form method="POST" action="{{ route('locale.switch', $code) }}">
                @csrf
                <button type="submit" lang="{{ $code }}" class="{{ $itemClass }}">{!! $label !!}</button>
            </form>
        @endif
    @endforeach
</div>
