<x-layouts.admin :title="$payment->reference" :crumb="__('Payments')">
    <x-ui.card>
        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <p class="text-3xl font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($payment->amount_minor, $payment->currency) }}</p>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-fg-secondary">
                    <x-ui.badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-ui.badge>
                    <span>{{ $payment->gatewayLabel() }}</span>
                    @if ($payment->verified_via)<span>· {{ __('Verified via :source', ['source' => $payment->verified_via->label()]) }}</span>@endif
                </p>
            </div>
            <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to payments') }}</a>
            @if ($payment->status->isOpen() && $payment->gateway_payment_id)
                @can('verify', $payment)
                    <form method="POST" action="{{ route('admin.payments.verify', $payment) }}">
                        @csrf
                        <x-ui.button size="sm" variant="secondary">{{ __('Check status with gateway') }}</x-ui.button>
                    </form>
                @endcan
            @endif
            @if ($payment->receipt)
                <x-ui.button size="sm" :href="route('admin.payments.receipt', $payment)">{{ __('Receipt :number', ['number' => $payment->receipt->number]) }}</x-ui.button>
            @endif
        </div>

        <dl class="mt-6 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                [__('Client'), $payment->customer?->company_name],
                [__('Project'), $payment->project ? $payment->project->name.' ('.$payment->project->reference.')' : null],
                [__('Milestone'), $payment->milestone?->title],
                [__('Payment link'), $payment->link?->reference],
                [__('Fee'), $payment->fee_minor !== null ? \App\Services\Money::plain($payment->fee_minor, $payment->currency) : __('Not reported by the gateway')],
                [__('Net'), $payment->net_minor !== null ? \App\Services\Money::plain($payment->net_minor, $payment->currency) : '—'],
                [__('Paid at'), $payment->paid_at?->translatedFormat('j M Y, H:i')],
                [__('Verified at'), $payment->verified_at?->translatedFormat('j M Y, H:i')],
                [__('Gateway reference'), $payment->gateway_payment_id],
            ] as [$label, $value])
                <div>
                    <dt class="text-fg-tertiary">{{ $label }}</dt>
                    <dd class="mt-0.5 break-all font-semibold text-fg" dir="auto">{{ $value ?? '—' }}</dd>
                </div>
            @endforeach
            @if ($payment->failure_reason)
                <div>
                    <dt class="text-fg-tertiary">{{ __('Failure reason') }}</dt>
                    <dd class="mt-0.5 font-mono text-fg-danger" dir="ltr">{{ $payment->failure_reason }}</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>

    <x-ui.card :title="__('History')">
        <ol class="flex flex-col gap-3" data-payment-events>
            @foreach ($payment->events as $event)
                <li class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-xs text-fg-tertiary">{{ $event->created_at?->translatedFormat('j M Y, H:i:s') }}</span>
                    @if ($event->from_status)
                        <x-ui.badge :tone="$event->from_status->tone()" :dot="false">{{ $event->from_status->label() }}</x-ui.badge>
                        <span aria-hidden="true" class="rtl:rotate-180">→</span>
                    @endif
                    <x-ui.badge :tone="$event->to_status->tone()" :dot="false">{{ $event->to_status->label() }}</x-ui.badge>
                    <span class="text-fg-secondary">{{ $event->source->label() }}@if ($event->user) · {{ $event->user->name }}@endif</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 text-xs text-fg-tertiary">{{ __('This history is append-only. Card details are never stored.') }}</p>
    </x-ui.card>
</x-layouts.admin>
