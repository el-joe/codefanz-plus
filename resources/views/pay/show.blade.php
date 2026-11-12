@php
    $stateMessages = [
        'used' => [__('This payment request has been paid'), __('Thank you. A receipt was issued for this payment. If you think this is a mistake, contact CodeFanz.')],
        'expired' => [__('This payment link has expired'), __('Ask CodeFanz for a new payment link. Nothing was charged.')],
        'cancelled' => [__('This payment link was cancelled'), __('CodeFanz withdrew this request. Contact us if you still need to pay.')],
    ];
    $checks = [__('You’ll complete payment on the provider’s secure page.'), __('We confirm payment only after verifying it with the gateway.'), __('CodeFanz never stores your card details.')];
@endphp

<x-layouts.checkout :title="__('Payment :reference', ['reference' => $link->reference])">
    <div class="grid items-start gap-6 lg:grid-cols-2" data-pay-page data-link-status="{{ $status->value }}">
        <x-ui.card class="shadow-sm">
            @if ($link->expires_at && $status === \App\Enums\PaymentLinkStatus::Active)
                <x-ui.badge tone="warning">{{ __('Expires :date', ['date' => $link->expires_at->translatedFormat('j M Y')]) }}</x-ui.badge>
            @endif
            <h1 class="mt-3 text-xl font-semibold text-fg">{{ __('Payment request from CodeFanz') }}</h1>

            <div class="mt-4 rounded-xl bg-brand-subtle p-5">
                <p class="text-sm text-fg-secondary">{{ __('Amount due') }}</p>
                <p class="mt-1 text-3xl font-bold text-fg sm:text-4xl" dir="ltr" data-amount>{{ \App\Services\Money::plain($link->amount_minor, $link->currency) }}</p>
                <p class="mt-2 flex items-center gap-1.5 text-xs text-fg-secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    {{ __('Amount set by CodeFanz · cannot be changed') }}
                </p>
            </div>

            <dl class="mt-4 divide-y divide-line-subtle text-sm">
                @foreach (array_filter([
                    [__('Reference'), $link->reference, 'ltr'],
                    [__('Project'), $link->project ? $link->project->name.' ('.$link->project->reference.')' : null, 'auto'],
                    [__('Milestone'), $link->milestone?->title, 'auto'],
                    [__('Billed to'), $billedTo, 'auto'],
                    [__('Description'), $link->description, 'auto'],
                ], fn ($row) => filled($row[1])) as [$label, $value, $direction])
                    <div class="flex items-center justify-between gap-4 py-3">
                        <dt class="text-fg-secondary">{{ $label }}</dt>
                        <dd class="text-end font-semibold text-fg" dir="{{ $direction }}">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card class="shadow-sm">
            @if ($status !== \App\Enums\PaymentLinkStatus::Active)
                @php([$stateTitle, $stateText] = $stateMessages[$status->value])
                <div class="flex flex-col items-center gap-3 py-6 text-center" data-link-state>
                    <span class="flex size-12 items-center justify-center rounded-full {{ $status === \App\Enums\PaymentLinkStatus::Used ? 'bg-success-subtle text-fg-success' : 'bg-muted text-fg-secondary' }}" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/>@if ($status === \App\Enums\PaymentLinkStatus::Used)<path d="m8 12 3 3 5-6"/>@else<path d="M12 7v5l3 2"/>@endif</svg>
                    </span>
                    <h2 class="text-lg font-semibold text-fg">{{ $stateTitle }}</h2>
                    <p class="max-w-sm text-sm text-fg-secondary">{{ $stateText }}</p>
                    <x-ui.button variant="secondary" size="sm" :href="route('contact', ['locale' => app()->getLocale()])">{{ __('Contact CodeFanz') }}</x-ui.button>
                </div>
            @elseif ($gateways === [])
                <div class="flex flex-col items-center gap-3 py-6 text-center" data-no-gateways>
                    <h2 class="text-lg font-semibold text-fg">{{ __('Online payment is not available right now') }}</h2>
                    <p class="max-w-sm text-sm text-fg-secondary">{{ __('No payment method is configured for this request yet. Please contact CodeFanz; nothing was charged.') }}</p>
                    <x-ui.button variant="secondary" size="sm" :href="route('contact', ['locale' => app()->getLocale()])">{{ __('Contact CodeFanz') }}</x-ui.button>
                </div>
            @else
                <h2 class="text-xl font-semibold text-fg">{{ __('Choose a payment method') }}</h2>

                @if ($cancelled)
                    <x-ui.alert tone="info" class="mt-4">{{ __('Checkout was cancelled. Nothing was charged; you can try again.') }}</x-ui.alert>
                @endif
                @error('gateway')
                    <x-ui.alert tone="danger" class="mt-4">{{ $message }}</x-ui.alert>
                @enderror

                <form method="POST" action="{{ route('pay.checkout', $link) }}" class="mt-4 flex flex-col gap-3" data-checkout-form>
                    @csrf
                    <fieldset class="flex flex-col gap-3">
                        <legend class="sr-only">{{ __('Payment method') }}</legend>
                        @foreach ($gateways as $key)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-4 has-checked:border-brand has-checked:bg-brand-subtle">
                                <input type="radio" name="gateway" value="{{ $key }}" class="size-4 accent-brand" @checked($loop->first) required>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-fg">{{ __(config("codefanz.payments.gateways.{$key}.method")) }}</span>
                                    <span class="block text-xs text-fg-secondary">{{ __(config("codefanz.payments.gateways.{$key}.description")) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                    <x-ui.button size="lg">{{ __('Pay :amount', ['amount' => \App\Services\Money::localized($link->amount_minor, $link->currency)]) }}</x-ui.button>
                </form>

                <ul class="mt-4 flex flex-col gap-2 text-xs text-fg-secondary">
                    @foreach ($checks as $check)
                        <li class="flex items-center gap-2"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="shrink-0 text-fg-success" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>{{ $check }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.checkout>
