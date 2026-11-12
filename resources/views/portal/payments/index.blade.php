<x-layouts.portal :title="__('Payment history')" :crumb="__('Payments')">
    <div class="grid gap-4 sm:grid-cols-3" data-payment-totals>
        <x-ui.card padding="p-5">
            <p class="text-xs text-fg-tertiary">{{ __('Total paid') }}</p>
            <p class="mt-1 text-2xl font-semibold text-fg-success" dir="ltr">{{ $totals->isEmpty() ? '—' : $totals->map(fn ($minor, $currency) => \App\Services\Money::plain($minor, $currency))->implode(' · ') }}</p>
        </x-ui.card>
        <x-ui.card padding="p-5">
            <p class="text-xs text-fg-tertiary">{{ __('Refunded') }}</p>
            <p class="mt-1 text-2xl font-semibold text-fg" dir="ltr">{{ $refunds->isEmpty() ? '—' : $refunds->map(fn ($minor, $currency) => \App\Services\Money::plain($minor, $currency))->implode(' · ') }}</p>
        </x-ui.card>
        <x-ui.card padding="p-5">
            <p class="text-xs text-fg-tertiary">{{ __('Last payment') }}</p>
            <p class="mt-1 text-2xl font-semibold text-fg">{{ $lastPaidAt?->translatedFormat('j M Y') ?? '—' }}</p>
        </x-ui.card>
    </div>

    @if ($payments->isEmpty())
        <x-ui.card padding="p-6">
            @include('portal._empty-module', ['title' => __('No payments yet'), 'description' => __('Your payment history and receipts will appear here after your first payment.'), 'icon' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'])
        </x-ui.card>
    @else
        <x-ui.card padding="p-0" class="overflow-x-auto">
            <table class="w-full text-sm" data-payments>
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Date') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Reference') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Method') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Milestone') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Amount') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-center font-semibold">{{ __('Receipt') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr class="border-t border-line-subtle" data-payment="{{ $payment->ulid }}">
                            <td class="px-4 py-3 text-fg-secondary">{{ ($payment->paid_at ?? $payment->created_at)->translatedFormat('j M Y') }}</td>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-fg" dir="ltr">{{ $payment->reference }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $payment->methodLabel() }} · {{ $payment->gatewayLabel() }}</td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $payment->project?->name }}@if ($payment->milestone) · {{ $payment->milestone->title }}@endif</td>
                            <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($payment->amount_minor, $payment->currency) }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-center">
                                @if ($payment->receipt)
                                    <a href="{{ route('portal.payments.receipt', $payment) }}" class="text-sm font-semibold text-fg-brand hover:underline" dir="ltr">{{ $payment->receipt->number }}</a>
                                @else
                                    <span class="text-fg-tertiary" aria-label="{{ __('No receipt') }}">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif

    <x-ui.alert tone="info" :title="__('About failed payments')">
        {{ __('A failed attempt is never charged. Receipts are issued only after the gateway confirms the payment.') }}
    </x-ui.alert>
</x-layouts.portal>
