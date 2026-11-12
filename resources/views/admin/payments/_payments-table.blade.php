{{-- Transactions table (A05). Expects $payments; $showClient (default true). --}}
@php($showClient = $showClient ?? true)
<table class="w-full text-sm" data-payments-table>
    <thead class="bg-subtle text-xs text-fg-tertiary">
        <tr>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Reference') }}</th>
            @if ($showClient)<th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Client · Project') }}</th>@endif
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Gateway') }}</th>
            <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Amount') }}</th>
            <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Fee') }}</th>
            <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Net') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Verified') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Date') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($payments as $payment)
            <tr class="border-t border-line-subtle {{ $payment->status === \App\Enums\PaymentStatus::Failed ? 'bg-danger-subtle' : '' }}" data-payment="{{ $payment->ulid }}">
                <td class="px-4 py-3"><a href="{{ route('admin.payments.show', $payment) }}" class="font-mono text-xs font-semibold text-fg hover:text-fg-brand" dir="ltr">{{ $payment->reference }}</a></td>
                @if ($showClient)
                    <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $payment->customer?->company_name }} · {{ $payment->project?->name }}@if ($payment->milestone) · {{ $payment->milestone->title }}@endif</td>
                @endif
                <td class="px-4 py-3 text-fg-secondary">{{ $payment->gatewayLabel() }}</td>
                <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($payment->amount_minor, $payment->currency) }}</td>
                <td class="px-4 py-3 text-end text-fg-secondary" dir="ltr">{{ $payment->fee_minor !== null ? \App\Services\Money::plain($payment->fee_minor, $payment->currency) : '—' }}</td>
                <td class="px-4 py-3 text-end text-fg" dir="ltr">{{ $payment->net_minor !== null ? \App\Services\Money::plain($payment->net_minor, $payment->currency) : '—' }}</td>
                <td class="px-4 py-3"><x-ui.badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-ui.badge></td>
                <td class="px-4 py-3 text-xs text-fg-secondary">
                    @if ($payment->verified_at && $payment->verified_via)
                        <span class="inline-flex items-center gap-1 text-fg-success"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>{{ $payment->verified_via->label() }}</span>
                    @else
                        <span aria-label="{{ __('Not verified') }}">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-fg-secondary">{{ ($payment->paid_at ?? $payment->created_at)->translatedFormat('j M Y') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
