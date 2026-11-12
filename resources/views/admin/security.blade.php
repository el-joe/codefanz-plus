<x-layouts.admin :title="__('Security')" :crumb="__('Account')">
    @if (session('status') === 'two-factor-required')
        <x-ui.alert tone="warning" :title="__('Two-factor authentication required')">{{ __('Your role requires two-factor authentication before you can use the admin area.') }}</x-ui.alert>
    @elseif (session('status') === 'two-factor-authentication-enabled')
        <x-ui.alert tone="info">{{ __('Scan the QR code with your authenticator app, then enter a code to finish.') }}</x-ui.alert>
    @elseif (session('status') === 'two-factor-authentication-confirmed')
        <x-ui.alert tone="success">{{ __('Two-factor authentication is now active.') }}</x-ui.alert>
    @endif

    <x-ui.card :title="__('Two-factor authentication')" class="max-w-2xl">
        @if ($user->hasConfirmedTwoFactor())
            <p class="text-sm text-fg-secondary">{{ __('Two-factor authentication is enabled for your account.') }}</p>
            <div class="mt-4 flex flex-col gap-2">
                <p class="text-sm font-semibold text-fg">{{ __('Recovery codes') }}</p>
                <p class="text-xs text-fg-tertiary">{{ __('Store these somewhere safe. Each code works once.') }}</p>
                <ul class="grid grid-cols-2 gap-1 rounded-lg bg-subtle p-4 font-mono text-sm text-fg" dir="ltr">
                    @foreach ($user->recoveryCodes() as $code)
                        <li>{{ $code }}</li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ url('user/two-factor-recovery-codes') }}">
                    @csrf
                    <x-ui.button variant="secondary" size="sm">{{ __('Regenerate recovery codes') }}</x-ui.button>
                </form>
            </div>
            @unless ($user->requiresTwoFactor())
                <form method="POST" action="{{ url('user/two-factor-authentication') }}" class="mt-6" data-confirm="{{ __('Turn off two-factor authentication?') }}" data-confirm-label="{{ __('Turn off') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" size="sm">{{ __('Turn off') }}</x-ui.button>
                </form>
            @endunless
        @elseif ($user->two_factor_secret)
            <div class="flex flex-col gap-4">
                <div class="w-fit rounded-lg bg-white p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ url('user/confirmed-two-factor-authentication') }}" class="flex max-w-sm flex-col gap-4">
                    @csrf
                    <x-ui.input name="code" :label="__('Authentication code')" inputmode="numeric" autocomplete="one-time-code" dir="ltr" required />
                    <x-ui.button>{{ __('Confirm and enable') }}</x-ui.button>
                </form>
            </div>
        @else
            <p class="text-sm text-fg-secondary">{{ __('Add a second step to sign-in using an authenticator app.') }}</p>
            <form method="POST" action="{{ url('user/two-factor-authentication') }}" class="mt-4">
                @csrf
                <x-ui.button>{{ __('Set up two-factor authentication') }}</x-ui.button>
            </form>
        @endif
    </x-ui.card>
</x-layouts.admin>
