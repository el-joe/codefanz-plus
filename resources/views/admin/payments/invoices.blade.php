<x-layouts.admin :title="__('Invoices')" :crumb="__('Payments')">
    @include('admin.payments._tabs')

    <form method="GET" action="{{ route('admin.invoices.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <div class="w-full sm:w-72">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Invoice number or client')" />
        </div>
        <div class="w-full sm:w-44">
            <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? null" :placeholder="__('All statuses')" :options="\App\Enums\InvoiceStatus::options()" />
        </div>
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($invoices->isEmpty())
            <x-ui.empty-state :title="__('No invoices yet')" :description="__('Draft an invoice from a milestone or a payment link, then issue it to give it a number.')" />
        @else
            @include('admin.payments._invoices-table', ['invoices' => $invoices])
        @endif
    </x-ui.card>

    {{ $invoices->links() }}
</x-layouts.admin>
