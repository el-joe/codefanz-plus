<x-layouts.admin :title="__('Refunds')" :crumb="__('Payments')">
    @include('admin.payments._tabs')

    @if ($awaiting > 0)
        <x-ui.alert tone="warning" :title="__('Refund awaiting finance approval')" data-refunds-awaiting>
            {{ __('Waiting for approval: :count. Only a finance approver who did not request a refund can approve it.', ['count' => $awaiting]) }}
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.refunds.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <div class="w-full sm:w-56">
            <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? null" :placeholder="__('All statuses')" :options="\App\Enums\RefundStatus::options()" />
        </div>
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($refunds->isEmpty())
            <x-ui.empty-state :title="__('No refunds yet')" :description="__('Request a refund from a collected payment’s page. A different finance user approves it before anything reaches the gateway.')" />
        @else
            @include('admin.payments._refunds-table', ['refunds' => $refunds])
        @endif
    </x-ui.card>

    {{ $refunds->links() }}

    @include('admin.payments._financial-controls')
</x-layouts.admin>
