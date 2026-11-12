<x-layouts.auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold text-fg">{{ __('Two-factor authentication') }}</h1>
        <p class="text-fg-secondary">{{ __('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>
    </div>

    <form method="POST" action="{{ route('two-factor.login') }}" class="flex flex-col gap-5">
        @csrf
        <x-ui.input name="code" :label="__('Authentication code')" inputmode="numeric" autocomplete="one-time-code" autofocus dir="ltr" />
        <x-ui.input name="recovery_code" :label="__('Or a recovery code')" autocomplete="off" dir="ltr" />
        <x-ui.button size="lg" class="w-full">{{ __('Verify') }}</x-ui.button>
    </form>
</x-layouts.auth>
