<x-layouts.auth :title="__('Confirm password')">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold text-fg">{{ __('Confirm your password') }}</h1>
        <p class="text-fg-secondary">{{ __('This is a secure area. Please confirm your password to continue.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf
        <x-ui.input name="password" type="password" :label="__('Password')" autocomplete="current-password" required autofocus />
        <x-ui.button size="lg" class="w-full">{{ __('Confirm') }}</x-ui.button>
    </form>
</x-layouts.auth>
