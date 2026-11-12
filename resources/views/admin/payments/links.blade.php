<x-layouts.admin :title="__('Payment links')" :crumb="__('Payments')">
    @include('admin.payments._tabs')

    @if ($errors->has('link'))
        <x-ui.alert tone="danger">{{ $errors->first('link') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.payment-links.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <div class="w-full sm:w-72">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Reference, description or client')" />
        </div>
        <div class="w-full sm:w-44">
            <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? null" :placeholder="__('All statuses')" :options="\App\Enums\PaymentLinkStatus::options()" />
        </div>
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($links->isEmpty())
            <x-ui.empty-state :title="__('No payment links yet')" :description="__('Create a payment link from a project’s milestones or payments tab.')" />
        @else
            @include('admin.payments._links-table', ['links' => $links])
        @endif
    </x-ui.card>

    {{ $links->links() }}
</x-layouts.admin>
