@php(\App\Services\ErrorPageLocale::apply(request()))
<x-layouts.error code="503" :title="__('We’ll be right back')" :message="__('We are making some improvements. Please check back in a few minutes.')">
    <x-ui.button :href="route('home', ['locale' => app()->getLocale()])">{{ __('Go to homepage') }}</x-ui.button>
</x-layouts.error>
