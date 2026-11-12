@if ($errors->has('link'))
    <x-ui.alert tone="danger">{{ $errors->first('link') }}</x-ui.alert>
@endif

@can('viewAny', \App\Models\Payment::class)
    <x-ui.card padding="p-0" class="overflow-x-auto">
        <div class="flex items-center gap-3 px-5 py-4">
            <h2 class="flex-1 text-lg font-semibold text-fg">{{ __('Payment links') }}</h2>
            @isset($linkDialog)
                <x-ui.button size="sm" type="button" data-dialog-open="payment-link-dialog">{{ __('Create payment link') }}</x-ui.button>
            @endisset
        </div>
        @if ($paymentLinks->isEmpty())
            <x-ui.empty-state :title="__('No payment links yet')" :description="__('Create a link for a milestone or a custom amount and send it to the client.')" />
        @else
            @include('admin.payments._links-table', ['links' => $paymentLinks, 'showClient' => false])
        @endif
    </x-ui.card>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <h2 class="px-5 py-4 text-lg font-semibold text-fg">{{ __('Transactions') }}</h2>
        @if ($payments->isEmpty())
            <x-ui.empty-state :title="__('No transactions yet')" :description="__('Payments appear here once a client starts a checkout from a payment link.')" />
        @else
            @include('admin.payments._payments-table', ['payments' => $payments, 'showClient' => false])
        @endif
    </x-ui.card>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <h2 class="px-5 py-4 text-lg font-semibold text-fg">{{ __('Invoices') }}</h2>
        @if ($invoices->isEmpty())
            <x-ui.empty-state :title="__('No invoices yet')" :description="__('Draft an invoice from a milestone or a payment link, then issue it to give it a number.')" />
        @else
            @include('admin.payments._invoices-table', ['invoices' => $invoices, 'showClient' => false])
        @endif
    </x-ui.card>
@else
    <x-ui.card>
        <x-ui.empty-state :title="__('Finance access needed')" :description="__('Payment links, transactions and invoices are visible to staff who create payment links or approve refunds.')" />
    </x-ui.card>
@endcan
