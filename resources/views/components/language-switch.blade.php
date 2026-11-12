@props(['alternates' => []])

@php
    $languages = \App\Models\Language::active();
    $current = app()->getLocale();
    $route = request()->route();
    $isLocalized = $route && in_array('locale.route', $route->gatherMiddleware(), true);
@endphp

<div class="flex items-center gap-1" aria-label="{{ __('Language') }}">
    @foreach ($languages as $code => $language)
        @continue($code === $current)
        @if ($isLocalized)
            <a href="{{ $alternates[$code] ?? route('home', ['locale' => $code]) }}"
               hreflang="{{ $code }}" lang="{{ $code }}"
               class="rounded-md px-2.5 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">{{ $language['native_name'] }}</a>
        @else
            <form method="POST" action="{{ route('locale.switch', $code) }}">
                @csrf
                <button type="submit" lang="{{ $code }}" class="rounded-md px-2.5 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">{{ $language['native_name'] }}</button>
            </form>
        @endif
    @endforeach
</div>
