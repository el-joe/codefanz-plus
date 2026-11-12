{{-- One invoice as a table row or a mobile card. Expects $invoice, $card. --}}
@php
    $overdue = $invoice->isOverdue();
    $payable = $invoice->status === \App\Enums\InvoiceStatus::Issued && $invoice->paymentLink?->isPayable();
    $context = trim(($invoice->project?->name ?? '').($invoice->milestone ? ' · '.$invoice->milestone->title : ''), ' ·');
    $badge = $overdue ? ['danger', __('Overdue')] : [$invoice->status->tone(), $invoice->status->label()];
@endphp
@if ($card)
    <li class="flex flex-col gap-2 p-4" data-invoice="{{ $invoice->ulid }}">
        <div class="flex items-center justify-between gap-3">
            <span class="font-mono text-xs font-semibold text-fg" dir="ltr">{{ $invoice->number }}</span>
            <x-ui.badge :tone="$badge[0]">{{ $badge[1] }}</x-ui.badge>
        </div>
        <p class="text-sm text-fg-secondary" dir="auto">{{ $context ?: '—' }}</p>
        <div class="flex items-center justify-between gap-3">
            <span class="text-base font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($invoice->total_minor, $invoice->currency) }}</span>
            <span class="text-xs text-fg-tertiary">{{ __('Due :date', ['date' => $invoice->due_at?->translatedFormat('j M') ?? '—']) }}</span>
        </div>
        <div class="flex gap-2">
            @if ($payable)<x-ui.button size="sm" :href="route('pay.show', $invoice->paymentLink)">{{ __('Pay now') }}</x-ui.button>@endif
            <x-ui.button size="sm" variant="secondary" :href="route('portal.invoices.print', $invoice)">{{ __('View') }}</x-ui.button>
        </div>
    </li>
@else
    <tr class="border-t border-line-subtle" data-invoice="{{ $invoice->ulid }}">
        <td class="px-4 py-3 font-mono text-xs font-semibold text-fg" dir="ltr">{{ $invoice->number }}</td>
        <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $context ?: '—' }}</td>
        <td class="px-4 py-3 text-fg-secondary">{{ $invoice->issued_at?->translatedFormat('j M Y') }}</td>
        <td class="px-4 py-3 {{ $overdue ? 'text-fg-danger' : 'text-fg-secondary' }}">{{ $invoice->due_at?->translatedFormat('j M Y') ?? '—' }}</td>
        <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($invoice->total_minor, $invoice->currency) }}</td>
        <td class="px-4 py-3"><x-ui.badge :tone="$badge[0]">{{ $badge[1] }}</x-ui.badge></td>
        <td class="px-4 py-3">
            <div class="flex justify-end gap-2">
                <x-ui.button size="sm" variant="ghost" :href="route('portal.invoices.print', $invoice)">{{ __('View') }}</x-ui.button>
                @if ($payable)<x-ui.button size="sm" :href="route('pay.show', $invoice->paymentLink)">{{ __('Pay now') }}</x-ui.button>@endif
            </div>
        </td>
    </tr>
@endif
