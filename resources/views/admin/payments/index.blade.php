<x-layouts.admin :title="__('Payments & transactions')" :crumb="__('Delivery')">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-payment-kpis>
        @foreach ([
            ['collected', __('Collected (this month)'), $kpis['collected'], null],
            ['links', __('Open payment links'), $kpis['open_links'], $kpis['open_links_total']],
            ['fees', __('Gateway fees (this month)'), $kpis['fees'], __('Kept separate from project revenue')],
            ['failed', __('Failed payments (this month)'), $kpis['failed'], __('Failed attempts are never charged')],
        ] as [$key, $label, $value, $hint])
            <x-ui.card padding="p-5" data-kpi="{{ $key }}">
                <p class="text-sm text-fg-secondary">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-fg" dir="ltr">{{ $value }}</p>
                @if ($hint)<p class="mt-1 text-xs text-fg-tertiary" dir="auto">{{ $hint }}</p>@endif
            </x-ui.card>
        @endforeach
    </div>

    @include('admin.payments._tabs', ['exportFilters' => $filters])

    <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-wrap items-end gap-3" role="search">
        <div class="w-full sm:w-72">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Reference, client or project')" />
        </div>
        <div class="w-full sm:w-44">
            <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? null" :placeholder="__('All statuses')" :options="\App\Enums\PaymentStatus::options()" />
        </div>
        <div class="w-full sm:w-44">
            <x-ui.select name="gateway" :label="__('Gateway')" :value="$filters['gateway'] ?? null" :placeholder="__('All gateways')" :options="$gatewayOptions" />
        </div>
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($payments->isEmpty())
            <x-ui.empty-state :title="__('No transactions yet')" :description="__('Payments appear here once a client starts a checkout from a payment link.')" />
        @else
            @include('admin.payments._payments-table', ['payments' => $payments])
        @endif
    </x-ui.card>

    {{ $payments->links() }}

    @include('admin.payments._financial-controls')
</x-layouts.admin>
