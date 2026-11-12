{{-- Payments section tabs (A05): transactions, payment links, invoices and refunds (with the approvals waiting). --}}
@php($awaitingRefunds = \App\Models\Refund::query()->where('status', \App\Enums\RefundStatus::Requested)->count())
<div class="flex flex-wrap items-center justify-between gap-3">
    @include('portal._tabs', ['label' => __('Payments sections'), 'tabs' => [
        [__('Transactions'), route('admin.payments.index'), request()->routeIs('admin.payments.*')],
        [__('Payment links'), route('admin.payment-links.index'), request()->routeIs('admin.payment-links.*')],
        [__('Invoices'), route('admin.invoices.index'), request()->routeIs('admin.invoices.*')],
        [$awaitingRefunds > 0 ? __('Refunds (:count)', ['count' => $awaitingRefunds]) : __('Refunds'), route('admin.refunds.index'), request()->routeIs('admin.refunds.*')],
    ]])
    @isset($exportFilters)
        @include('admin.payments._export-button', ['filters' => $exportFilters])
    @endisset
</div>
