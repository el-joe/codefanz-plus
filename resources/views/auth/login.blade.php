<x-layouts.auth :title="__('Sign in')">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold text-fg">{{ __('Welcome back') }}</h1>
        <p class="text-fg-secondary">{{ __('Sign in to your CodeFanz account.') }}</p>
    </div>

    @if (session('status'))
        <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf
        <x-ui.input name="email" type="email" :label="__('Email')" autocomplete="username" required autofocus dir="ltr" />
        <x-ui.input name="password" type="password" :label="__('Password')" autocomplete="current-password" required />

        <div class="flex items-center justify-between gap-4">
            <label class="inline-flex items-center gap-2 text-sm text-fg">
                <input type="checkbox" name="remember" class="size-4 rounded border-line-strong accent-brand-600">
                {{ __('Remember me') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Forgot password?') }}</a>
        </div>

        <x-ui.button size="lg" class="w-full">{{ __('Sign in') }}</x-ui.button>
    </form>

    <p class="text-xs text-fg-tertiary">{{ __('Protected with two-factor authentication when enabled.') }}</p>
    <hr class="border-line">
    <p class="text-sm text-fg-secondary">{{ __('New client? Your project manager sends your invitation.') }}
        <a href="mailto:hello@codefanz.com" class="font-semibold text-fg-brand hover:underline">{{ __('Request access') }}</a>
    </p>
</x-layouts.auth>
