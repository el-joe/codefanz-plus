<x-layouts.admin :title="__('Customers')" :crumb="__('CRM')">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex flex-wrap items-end gap-3" role="search">
            <div class="w-full sm:w-72">
                <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Name, email or reference')" />
            </div>
            <div class="w-full sm:w-44">
                <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? null" :placeholder="__('All statuses')" :options="\App\Enums\CustomerStatus::options()" />
            </div>
            <div class="w-full sm:w-44">
                <x-ui.select name="type" :label="__('Customer type')" :value="$filters['type'] ?? null" :placeholder="__('All types')" :options="\App\Enums\CustomerType::options()" />
            </div>
            <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
        </form>
        @can('create', \App\Models\Customer::class)
            <x-ui.button :href="route('admin.customers.create')">{{ __('Add customer') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($customers->isEmpty())
            <x-ui.empty-state :title="__('No customers yet')" :description="__('Convert a won lead or add a customer manually.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Customer') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Customer type') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Country') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Portal users') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $customer->company_name }}</a>
                                <p class="text-xs text-fg-tertiary"><span dir="ltr">{{ $customer->reference }}</span>@if ($customer->email) · <span dir="ltr">{{ $customer->email }}</span>@endif</p>
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $customer->type->label() }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $customer->country ? $countries->name($customer->country) : '—' }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $customer->portal_users_count }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$customer->status->tone()">{{ $customer->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $customer->created_at->translatedFormat('j M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $customers->links() }}
</x-layouts.admin>
