@php(\App\Services\ErrorPageLocale::apply(request()))
<x-layouts.error code="403" :title="__('You don’t have access to this page')" :message="__('If you think you should, sign in with the right account or contact your administrator.')">
    <x-ui.button :href="route('home', ['locale' => app()->getLocale()])">{{ __('Go to homepage') }}</x-ui.button>
</x-layouts.error>
