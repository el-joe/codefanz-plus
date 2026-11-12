{{-- Payments section tabs (A05): transactions, payment links, invoices. Refunds arrive with the refund approval workflow. --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    @include('portal._tabs', ['label' => __('Payments sections'), 'tabs' => [
        [__('Transactions'), route('admin.payments.index'), request()->routeIs('admin.payments.*')],
        [__('Payment links'), route('admin.payment-links.index'), request()->routeIs('admin.payment-links.*')],
        [__('Invoices'), route('admin.invoices.index'), request()->routeIs('admin.invoices.*')],
    ]])
</div>
