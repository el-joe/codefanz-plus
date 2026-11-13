<x-layouts.document :title="__('Credit note :number', ['number' => $creditNote->number])" :backUrl="$backUrl">
    <div class="flex flex-wrap items-start justify-between gap-6">
        @include('billing._seller')
        <div class="text-end">
            <p class="text-3xl font-bold">{{ __('Credit note') }}</p>
            <p class="mt-1 font-mono text-sm font-semibold" dir="ltr" data-credit-note-number>{{ $creditNote->number }}</p>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
        @include('billing._bill-to', ['billTo' => $creditNote->customer_snapshot, 'heading' => __('Credited to')])
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:justify-self-end">
            <dt class="text-ink-500">{{ __('Date') }}</dt><dd class="font-semibold">{{ $creditNote->issued_at->translatedFormat('j M Y') }}</dd>
            <dt class="text-ink-500">{{ __('Payment') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $creditNote->payment?->reference }}</dd>
            <dt class="text-ink-500">{{ __('Refund') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $creditNote->refund?->reference }}</dd>
        </dl>
    </div>

    <table class="mt-8 w-full text-sm">
        <thead class="border-b-2 border-ink-900 text-xs text-ink-500">
            <tr>
                <th scope="col" class="py-2 text-start font-semibold">{{ __('Reason') }}</th>
                <th scope="col" class="py-2 text-end font-semibold">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-ink-200">
                <td class="py-3" dir="auto">{{ $creditNote->reason }}</td>
                <td class="py-3 text-end" dir="ltr">{{ \App\Services\Money::plain($creditNote->amount_minor, $creditNote->currency) }}</td>
            </tr>
        </tbody>
    </table>

    <dl class="ms-auto mt-4 grid w-full max-w-xs grid-cols-2 gap-y-1.5 text-sm">
        <dt class="border-t-2 border-ink-900 pt-2 font-semibold">{{ __('Total credited') }}</dt><dd class="border-t-2 border-ink-900 pt-2 text-end text-lg font-bold" dir="ltr" data-credit-note-amount>{{ \App\Services\Money::plain($creditNote->amount_minor, $creditNote->currency) }}</dd>
    </dl>

    <p class="mt-10 border-t border-ink-200 pt-4 text-xs text-ink-500">{{ __('Amounts in :currency. This credit note records a refund approved by CodeFanz finance.', ['currency' => $creditNote->currency]) }}</p>
</x-layouts.document>
