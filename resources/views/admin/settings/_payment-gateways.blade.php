{{-- Settings → Payments: one card per gateway with its honest status, connection test, webhook URL, currencies and
     credential fields. Saving needs a recently confirmed password; secrets are write-only. --}}
<x-ui.alert tone="warning" :title="__('Financial credentials')" data-payment-credentials-notice>
    {{ __('Changes here are audited without secret values and clear the gateway’s connection test. You confirmed your password to open this page.') }}
</x-ui.alert>

@foreach ($paymentGateways as $key => $gateway)
    <form method="POST" action="{{ route('admin.settings.payments.test', $key) }}" id="gateway-test-{{ $key }}" class="hidden">@csrf</form>
@endforeach

<form method="POST" action="{{ route('admin.settings.update', 'payments') }}" class="flex flex-col gap-6" data-payment-gateways>
    @csrf
    @method('PUT')

    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ($paymentGateways as $key => $gateway)
            <x-ui.card data-gateway="{{ $key }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-fg">{{ $gateway['label'] }}</h2>
                    @switch($gateway['status'])
                        @case('connected')
                            <x-ui.badge tone="success" data-gateway-status="connected">{{ __('Connected (verified :date)', ['date' => $gateway['verified_at']->translatedFormat('j M Y, H:i')]) }} · {{ $gateway['mode'] === 'live' ? __('Live') : __('Sandbox') }}</x-ui.badge>
                            @break
                        @case('sandbox')<x-ui.badge tone="info" data-gateway-status="sandbox">{{ __('Sandbox · test mode') }}</x-ui.badge>@break
                        @case('live_unverified')<x-ui.badge tone="warning" data-gateway-status="live_unverified">{{ __('Live · not verified') }}</x-ui.badge>@break
                        @case('incomplete')<x-ui.badge tone="warning" data-gateway-status="incomplete">{{ __('Incomplete') }}</x-ui.badge>@break
                        @default<x-ui.badge data-gateway-status="requires_credentials">{{ __('Requires credentials') }}</x-ui.badge>
                    @endswitch
                </div>

                <dl class="mt-3 grid gap-2 text-xs">
                    <div>
                        <dt class="text-fg-tertiary">{{ __('Webhook endpoint (add it in the gateway dashboard):') }}</dt>
                        <dd><code class="mt-1 block break-all rounded bg-subtle px-2 py-1 font-mono text-fg" dir="ltr">{{ $gateway['webhook_url'] }}</code></dd>
                    </div>
                    <div class="flex flex-wrap gap-x-2">
                        <dt class="text-fg-tertiary">{{ __('Currencies') }}:</dt>
                        <dd class="font-semibold text-fg" dir="ltr" data-gateway-currencies>{{ $gateway['currencies'] === [] ? '—' : implode(' · ', $gateway['currencies']) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-col gap-5">
                    @include('admin.settings._fields', ['fields' => array_filter($fields, fn ($field) => ($field['section'] ?? null) === $key)])
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-line-subtle pt-4">
                    @if ($gateway['testable'])
                        <x-ui.button type="submit" form="gateway-test-{{ $key }}" variant="secondary" size="sm" :disabled="! $gateway['configured']">{{ __('Test connection') }}</x-ui.button>
                    @endif
                    <p class="text-xs text-fg-tertiary">{{ __('The test makes a harmless authenticated API call. Payments are still confirmed with the gateway API before they count.') }}</p>
                </div>
            </x-ui.card>
        @endforeach
    </div>

    <div><x-ui.button>{{ __('Save payment settings') }}</x-ui.button></div>
</form>
