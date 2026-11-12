{{-- Invoices table. Expects $invoices; $showClient (default true). --}}
@php($showClient = $showClient ?? true)
<table class="w-full text-sm" data-invoices-table>
    <thead class="bg-subtle text-xs text-fg-tertiary">
        <tr>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Invoice') }}</th>
            @if ($showClient)<th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Client · Project') }}</th>@endif
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Issued') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Due') }}</th>
            <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Total') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoices as $invoice)
            <tr class="border-t border-line-subtle" data-invoice="{{ $invoice->ulid }}">
                <td class="px-4 py-3"><a href="{{ route('admin.invoices.show', $invoice) }}" class="font-mono text-xs font-semibold text-fg hover:text-fg-brand" dir="ltr">{{ $invoice->displayNumber() }}</a></td>
                @if ($showClient)
                    <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $invoice->customer?->company_name }}@if ($invoice->project) · {{ $invoice->project->name }}@endif</td>
                @endif
                <td class="px-4 py-3 text-fg-secondary">{{ $invoice->issued_at?->translatedFormat('j M Y') ?? '—' }}</td>
                <td class="px-4 py-3 {{ $invoice->isOverdue() ? 'text-fg-danger' : 'text-fg-secondary' }}">{{ $invoice->due_at?->translatedFormat('j M Y') ?? '—' }}</td>
                <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($invoice->total_minor, $invoice->currency) }}</td>
                <td class="px-4 py-3"><x-ui.badge :tone="$invoice->isOverdue() ? 'danger' : $invoice->status->tone()">{{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}</x-ui.badge></td>
            </tr>
        @endforeach
    </tbody>
</table>
