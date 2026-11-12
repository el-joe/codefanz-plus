<x-layouts.auth :title="__('Choose a new password')">
    <h1 class="text-3xl font-bold text-fg">{{ __('Choose a new password') }}</h1>

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input name="email" type="email" :label="__('Email')" :value="$request->email" autocomplete="username" required dir="ltr" />
        <x-ui.input name="password" type="password" :label="__('New password')" autocomplete="new-password" required :helper="__('At least 12 characters. Avoid common or breached passwords.')" />
        <x-ui.input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" required />
        <x-ui.button size="lg" class="w-full">{{ __('Reset password') }}</x-ui.button>
    </form>
</x-layouts.auth>
