@php($billTo = $invoice->billTo())
<x-layouts.document :title="__('Invoice :number', ['number' => $invoice->displayNumber()])" :backUrl="$backUrl">
    <div class="flex flex-wrap items-start justify-between gap-6">
        @include('billing._seller')
        <div class="text-end">
            <p class="text-3xl font-bold">{{ __('Invoice') }}</p>
            <p class="mt-1 font-mono text-sm font-semibold" dir="ltr" data-invoice-number>{{ $invoice->displayNumber() }}</p>
            <p class="mt-2 text-sm font-semibold {{ $invoice->status === \App\Enums\InvoiceStatus::Paid ? 'text-green-700' : 'text-ink-600' }}">{{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}</p>
        </div>
    </div>

    @if ($invoice->status === \App\Enums\InvoiceStatus::Draft)
        <p class="mt-6 rounded-md border border-ink-300 p-3 text-sm text-ink-600">{{ __('Draft — not issued. Numbers and customer details are fixed when the invoice is issued.') }}</p>
    @elseif ($invoice->status === \App\Enums\InvoiceStatus::Void)
        <p class="mt-6 rounded-md border border-ink-300 p-3 text-sm text-ink-600">{{ __('Void: :reason', ['reason' => $invoice->void_reason]) }}</p>
    @endif

    <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2">
        @include('billing._bill-to', ['billTo' => $billTo, 'heading' => __('Bill to')])
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:justify-self-end">
            <dt class="text-ink-500">{{ __('Issued') }}</dt><dd class="font-semibold">{{ $invoice->issued_at?->translatedFormat('j M Y') ?? '—' }}</dd>
            <dt class="text-ink-500">{{ __('Due') }}</dt><dd class="font-semibold">{{ $invoice->due_at?->translatedFormat('j M Y') ?? '—' }}</dd>
            @if ($invoice->project)<dt class="text-ink-500">{{ __('Project') }}</dt><dd class="font-semibold" dir="auto">{{ $invoice->project->name }}</dd>@endif
            @if ($invoice->paymentLink)<dt class="text-ink-500">{{ __('Payment link') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $invoice->paymentLink->reference }}</dd>@endif
        </dl>
    </div>

    <table class="mt-8 w-full text-sm">
        <thead class="border-b-2 border-ink-900 text-xs text-ink-500">
            <tr>
                <th scope="col" class="py-2 text-start font-semibold">{{ __('Description') }}</th>
                <th scope="col" class="py-2 text-end font-semibold">{{ __('Qty') }}</th>
                <th scope="col" class="py-2 text-end font-semibold">{{ __('Unit price') }}</th>
                <th scope="col" class="py-2 text-end font-semibold">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr class="border-b border-ink-200">
                    <td class="py-3" dir="auto">{{ $item->description }}</td>
                    <td class="py-3 text-end">{{ $item->quantity }}</td>
                    <td class="py-3 text-end" dir="ltr">{{ \App\Services\Money::plain($item->unit_minor, $invoice->currency) }}</td>
                    <td class="py-3 text-end" dir="ltr">{{ \App\Services\Money::plain($item->total_minor, $invoice->currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <dl class="ms-auto mt-4 grid w-full max-w-xs grid-cols-2 gap-y-1.5 text-sm">
        <dt class="text-ink-500">{{ __('Subtotal') }}</dt><dd class="text-end" dir="ltr">{{ \App\Services\Money::plain($invoice->subtotal_minor, $invoice->currency) }}</dd>
        <dt class="text-ink-500">{{ __('Tax (:rate%)', ['rate' => rtrim(rtrim(number_format($invoice->tax_rate_bp / 100, 2, '.', ''), '0'), '.')]) }}</dt><dd class="text-end" dir="ltr">{{ \App\Services\Money::plain($invoice->tax_minor, $invoice->currency) }}</dd>
        <dt class="border-t-2 border-ink-900 pt-2 font-semibold">{{ __('Total') }}</dt><dd class="border-t-2 border-ink-900 pt-2 text-end text-lg font-bold" dir="ltr" data-invoice-total>{{ \App\Services\Money::plain($invoice->total_minor, $invoice->currency) }}</dd>
    </dl>

    @if (filled($invoice->notes))
        <div class="mt-8 text-sm">
            <p class="font-semibold">{{ __('Notes') }}</p>
            <p class="mt-1 whitespace-pre-line text-ink-600" dir="auto">{{ $invoice->notes }}</p>
        </div>
    @endif

    <p class="mt-10 border-t border-ink-200 pt-4 text-xs text-ink-500">{{ __('Amounts in :currency. Payments are confirmed with the payment gateway before an invoice is marked paid.', ['currency' => $invoice->currency]) }}</p>
</x-layouts.document>
