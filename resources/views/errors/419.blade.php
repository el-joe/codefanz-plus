@php(\App\Services\ErrorPageLocale::apply(request()))
<x-layouts.error code="419" :title="__('This page has expired')" :message="__('For your security the form timed out. Go back, refresh the page and try again.')">
    <x-ui.button :href="route('home', ['locale' => app()->getLocale()])">{{ __('Go to homepage') }}</x-ui.button>
</x-layouts.error>
