@php($paid = $payment->status === \App\Enums\PaymentStatus::Paid)
@php($failed = in_array($payment->status, [\App\Enums\PaymentStatus::Failed, \App\Enums\PaymentStatus::Cancelled], true))
@php($offlineReference = $payment->metadata['offline_reference'] ?? null)
@php($offlineExpiresAt = isset($payment->metadata['offline_reference_expires_at']) ? \Illuminate\Support\Carbon::parse($payment->metadata['offline_reference_expires_at']) : null)

<x-layouts.checkout :title="$paid ? __('Payment confirmed') : __('Confirming your payment')">
    <x-ui.card class="mx-auto max-w-lg text-center shadow-sm" data-payment-result="{{ $payment->status->value }}">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full {{ $paid ? 'bg-success-subtle text-fg-success' : ($failed ? 'bg-danger-subtle text-fg-danger' : 'bg-info-subtle text-fg-info') }}" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/>@if ($paid)<path d="m8 12 3 3 5-6"/>@elseif ($failed)<path d="m15 9-6 6M9 9l6 6"/>@else<path d="M12 7v5l3 2"/>@endif</svg>
        </span>

        @if (! $paid && ! $failed && $offlineReference)
            <h1 class="mt-4 text-2xl font-semibold text-fg">{{ __('Pay with your Fawry reference number') }}</h1>
            <p class="mt-2 text-sm text-fg-secondary">{{ __('Pay this amount at any Fawry outlet or in the myFawry app using the reference number below. We confirm the payment as soon as Fawry notifies us and we verify it.') }}</p>
            <div class="mt-5 rounded-xl border-2 border-dashed border-line-strong px-4 py-4" data-offline-reference>
                <p class="text-xs text-fg-tertiary">{{ __('Reference number') }}</p>
                <p class="mt-1 font-mono text-3xl font-bold tracking-wider text-fg" dir="ltr">{{ $offlineReference }}</p>
                @if ($offlineExpiresAt)
                    <p class="mt-2 text-sm text-fg-secondary" data-offline-reference-expiry>{{ __('Pay before :date', ['date' => $offlineExpiresAt->translatedFormat('j M Y, H:i')]) }}</p>
                @endif
            </div>
        @elseif ($paid)
            <h1 class="mt-4 text-2xl font-semibold text-fg">{{ __('Payment confirmed') }}</h1>
            <p class="mt-2 text-sm text-fg-secondary">{{ __('We verified your payment with the gateway. A receipt is ready for you.') }}</p>
        @elseif ($failed)
            <h1 class="mt-4 text-2xl font-semibold text-fg">{{ __('The payment did not go through') }}</h1>
            <p class="mt-2 text-sm text-fg-secondary">{{ __('Nothing was charged. You can try again with the same link.') }}</p>
        @else
            <h1 class="mt-4 text-2xl font-semibold text-fg">{{ __('We’re confirming your payment') }}</h1>
            <p class="mt-2 text-sm text-fg-secondary">{{ __('The gateway has not confirmed this payment yet. We mark it paid only after verifying it with the gateway — this page does not. Refresh in a moment, or check your portal later.') }}</p>
        @endif

        <dl class="mt-5 divide-y divide-line-subtle rounded-xl bg-subtle px-4 text-sm">
            @foreach (array_filter([
                [__('Amount'), \App\Services\Money::plain($payment->amount_minor, $payment->currency), 'ltr'],
                [__('Receipt'), $payment->receipt?->number, 'ltr'],
                [__('Reference'), $payment->reference, 'ltr'],
                [__('Milestone'), $payment->milestone?->title, 'auto'],
            ], fn ($row) => filled($row[1])) as [$label, $value, $direction])
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-fg-secondary">{{ $label }}</dt>
                    <dd class="font-semibold text-fg" dir="{{ $direction }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-5 flex flex-wrap justify-center gap-3">
            @if ($receiptUrl)
                <x-ui.button variant="secondary" :href="$receiptUrl">{{ __('View receipt') }}</x-ui.button>
            @elseif ($failed)
                <x-ui.button :href="route('pay.show', $link)">{{ __('Try again') }}</x-ui.button>
            @else
                <x-ui.button variant="secondary" :href="route('pay.return', [$link, $payment])">{{ __('Refresh status') }}</x-ui.button>
            @endif
            <x-ui.button :href="route('portal.dashboard')">{{ __('Go to my portal') }} <span aria-hidden="true" class="rtl:rotate-180">→</span></x-ui.button>
        </div>
    </x-ui.card>
</x-layouts.checkout>
