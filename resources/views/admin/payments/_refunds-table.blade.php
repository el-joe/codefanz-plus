{{-- Refunds table. Expects $refunds; $showPayment (default true). --}}
@php($showPayment = $showPayment ?? true)
<table class="w-full text-sm" data-refunds-table>
    <thead class="bg-subtle text-xs text-fg-tertiary">
        <tr>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Refund') }}</th>
            @if ($showPayment)<th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Payment · Client') }}</th>@endif
            <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Amount') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Reason') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Requested · Decided') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Actions') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($refunds as $refund)
            <tr class="border-t border-line-subtle align-top {{ $refund->status === \App\Enums\RefundStatus::Requested ? 'bg-warning-subtle' : '' }}" data-refund="{{ $refund->ulid }}">
                <td class="px-4 py-3">
                    <span class="font-mono text-xs font-semibold text-fg" dir="ltr">{{ $refund->reference }}</span>
                    @if ($refund->creditNote)
                        <a href="{{ route('admin.credit-notes.print', $refund->creditNote) }}" class="mt-1 block font-mono text-xs text-fg-brand hover:underline" dir="ltr" data-credit-note>{{ $refund->creditNote->number }}</a>
                    @endif
                </td>
                @if ($showPayment)
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.payments.show', $refund->payment) }}" class="font-mono text-xs font-semibold text-fg hover:text-fg-brand" dir="ltr">{{ $refund->payment->reference }}</a>
                        <span class="block text-xs text-fg-secondary" dir="auto">{{ $refund->customer?->company_name }}</span>
                    </td>
                @endif
                <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($refund->amount_minor, $refund->currency) }}</td>
                <td class="max-w-xs px-4 py-3 text-fg-secondary" dir="auto">{{ $refund->reason }}</td>
                <td class="px-4 py-3">
                    <x-ui.badge :tone="$refund->status->tone()">{{ $refund->status->label() }}</x-ui.badge>
                    @if ($refund->failure_reason && $refund->status !== \App\Enums\RefundStatus::Succeeded)
                        <span class="mt-1 block font-mono text-xs text-fg-tertiary" dir="ltr">{{ $refund->failure_reason }}</span>
                    @endif
                    @if ($refund->manual_reference)
                        <span class="mt-1 block text-xs text-fg-tertiary">{{ __('Manual · :reference', ['reference' => $refund->manual_reference]) }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-fg-secondary">
                    <span class="block">{{ $refund->requester?->name ?? '—' }} · {{ $refund->created_at?->translatedFormat('j M Y') }}</span>
                    @if ($refund->decider)
                        <span class="block">{{ $refund->decider->name }} · {{ $refund->decided_at?->translatedFormat('j M Y') }}</span>
                    @endif
                    @if ($refund->decision_note)<span class="block text-fg-tertiary" dir="auto">{{ $refund->decision_note }}</span>@endif
                </td>
                <td class="px-4 py-3">@include('admin.payments._refund-actions', ['refund' => $refund])</td>
            </tr>
        @endforeach
    </tbody>
</table>
