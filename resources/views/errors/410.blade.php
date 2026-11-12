@php(\App\Services\ErrorPageLocale::apply(request()))
<x-layouts.error code="410" :title="__('This page has been removed')" :message="__('The content that used to be here is no longer available.')">
    <x-ui.button :href="route('home', ['locale' => app()->getLocale()])">{{ __('Go to homepage') }}</x-ui.button>
</x-layouts.error>
