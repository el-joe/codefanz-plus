<x-layouts.auth :title="__('Forgot password')">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-bold text-fg">{{ __('Reset your password') }}</h1>
        <p class="text-fg-secondary">{{ __('Enter your email and we will send you a reset link.') }}</p>
    </div>

    @if (session('status'))
        <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf
        <x-ui.input name="email" type="email" :label="__('Email')" autocomplete="username" required autofocus dir="ltr" />
        <x-ui.button size="lg" class="w-full">{{ __('Email reset link') }}</x-ui.button>
    </form>

    <a href="{{ route('login') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to sign in') }}</a>
</x-layouts.auth>
