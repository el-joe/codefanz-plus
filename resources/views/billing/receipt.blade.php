<x-layouts.document :title="__('Receipt :number', ['number' => $receipt->number])" :backUrl="$backUrl">
    <div class="flex flex-wrap items-start justify-between gap-6">
        @include('billing._seller')
        <div class="text-end">
            <p class="text-3xl font-bold">{{ __('Receipt') }}</p>
            <p class="mt-1 font-mono text-sm font-semibold" dir="ltr" data-receipt-number>{{ $receipt->number }}</p>
            <p class="mt-2 text-sm font-semibold text-green-700">{{ __('Paid') }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        @include('billing._bill-to', ['billTo' => $receipt->customer_snapshot, 'heading' => __('Received from')])
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:justify-self-end">
            <dt class="text-ink-500">{{ __('Date') }}</dt><dd class="font-semibold">{{ ($payment->paid_at ?? $receipt->issued_at)->translatedFormat('j M Y') }}</dd>
            <dt class="text-ink-500">{{ __('Payment') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $payment->reference }}</dd>
            <dt class="text-ink-500">{{ __('Method') }}</dt><dd class="font-semibold">{{ $payment->methodLabel() }} · {{ $payment->gatewayLabel() }}</dd>
            @if ($payment->link)<dt class="text-ink-500">{{ __('Payment link') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $payment->link->reference }}</dd>@endif
        </dl>
    </div>

    <table class="mt-8 w-full text-sm">
        <thead class="border-b-2 border-ink-900 text-xs text-ink-500">
            <tr>
                <th scope="col" class="py-2 text-start font-semibold">{{ __('Description') }}</th>
                <th scope="col" class="py-2 text-end font-semibold">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-ink-200">
                <td class="py-3" dir="auto">
                    {{ $payment->link?->description ?: __('Payment :reference', ['reference' => $payment->reference]) }}
                    @if ($payment->project)<span class="block text-xs text-ink-500">{{ $payment->project->name }}@if ($payment->milestone) · {{ $payment->milestone->title }}@endif</span>@endif
                </td>
                <td class="py-3 text-end" dir="ltr">{{ \App\Services\Money::plain($receipt->amount_minor, $receipt->currency) }}</td>
            </tr>
        </tbody>
    </table>

    <dl class="ms-auto mt-4 grid w-full max-w-xs grid-cols-2 gap-y-1.5 text-sm">
        <dt class="border-t-2 border-ink-900 pt-2 font-semibold">{{ __('Amount paid') }}</dt><dd class="border-t-2 border-ink-900 pt-2 text-end text-lg font-bold" dir="ltr" data-receipt-amount>{{ \App\Services\Money::plain($receipt->amount_minor, $receipt->currency) }}</dd>
        @if ($payment->refunded_minor > 0)
            <dt class="text-ink-500">{{ __('Refunded') }}</dt><dd class="text-end" dir="ltr">{{ \App\Services\Money::plain($payment->refunded_minor, $payment->currency) }}</dd>
        @endif
    </dl>

    <p class="mt-10 border-t border-ink-200 pt-4 text-xs text-ink-500">{{ __('This payment was verified with :gateway on :date. CodeFanz never stores card details.', ['gateway' => $payment->gatewayLabel(), 'date' => $payment->verified_at?->translatedFormat('j M Y, H:i') ?? '—']) }}</p>
</x-layouts.document>
