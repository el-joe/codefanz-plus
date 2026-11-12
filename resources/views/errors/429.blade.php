@php(\App\Services\ErrorPageLocale::apply(request()))
<x-layouts.error code="429" :title="__('Too many requests')" :message="__('Please wait a minute before trying again.')">
    <x-ui.button :href="route('home', ['locale' => app()->getLocale()])">{{ __('Go to homepage') }}</x-ui.button>
</x-layouts.error>
