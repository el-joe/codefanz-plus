@php
    $languages = \App\Models\Language::active()->map(fn ($language) => $language['native_name']);
@endphp

<x-layouts.admin :title="$customer->exists ? __('Edit :reference', ['reference' => $customer->reference]) : __('Add customer')" :crumb="__('Customers')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lead') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($lead)
        <x-ui.alert tone="info" :title="__('Converting a won lead')">
            {{ __('Details are prefilled from lead :reference. Review them before saving; the lead will link to this customer.', ['reference' => $lead->reference]) }}
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ $customer->exists ? route('admin.customers.update', $customer) : route('admin.customers.store') }}" class="grid gap-6 xl:grid-cols-[1fr_340px]">
        @csrf
        @if ($customer->exists)
            @method('PUT')
        @endif
        @if ($lead)
            <input type="hidden" name="lead" value="{{ $lead->ulid }}">
        @endif

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Customer')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="type" :label="__('Customer type')" :options="\App\Enums\CustomerType::options()" :value="$customer->type?->value" />
                    <x-ui.input name="company_name" :label="__('Company or customer name')" :value="$customer->company_name" required maxlength="160" dir="auto" />
                    <x-ui.input name="contact_name" :label="__('Primary contact')" :value="$customer->contact_name" maxlength="120" dir="auto" />
                    <x-ui.input name="email" type="email" :label="__('Email')" :value="$customer->email" maxlength="255" dir="ltr" />
                    <x-ui.input name="phone" type="tel" :label="__('Phone')" :value="$customer->phone" maxlength="30" dir="ltr" />
                    <x-ui.select name="preferred_locale" :label="__('Preferred language')" :options="$languages" :value="$customer->preferred_locale" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Billing details')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="country" :label="__('Country')" :options="$countries" :value="$customer->country" :placeholder="__('Unknown')" />
                    <x-ui.input name="tax_id" :label="__('Tax ID (optional)')" :value="$customer->tax_id" maxlength="50" dir="ltr" />
                    <x-ui.input name="billing_address_line1" :label="__('Address line 1')" :value="$customer->billing_address_line1" maxlength="200" dir="auto" />
                    <x-ui.input name="billing_address_line2" :label="__('Address line 2')" :value="$customer->billing_address_line2" maxlength="200" dir="auto" />
                    <x-ui.input name="billing_city" :label="__('City')" :value="$customer->billing_city" maxlength="100" dir="auto" />
                    <x-ui.input name="billing_state" :label="__('State / region')" :value="$customer->billing_state" maxlength="100" dir="auto" />
                    <x-ui.input name="billing_postal_code" :label="__('Postal code')" :value="$customer->billing_postal_code" maxlength="20" dir="ltr" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Internal notes')">
                @include('admin._textarea', ['name' => 'notes', 'id' => 'notes', 'label' => __('Notes'), 'value' => $customer->notes, 'rows' => 4, 'max' => 5000, 'help' => __('Visible to staff only.')])
            </x-ui.card>
        </div>

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Status')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="status" :label="__('Status')" :options="\App\Enums\CustomerStatus::options()" :value="$customer->status?->value" :helper="__('Portal users of an inactive customer cannot sign in to the portal.')" />
                    <x-ui.button>{{ $customer->exists ? __('Save changes') : __('Create customer') }}</x-ui.button>
                    <a href="{{ $customer->exists ? route('admin.customers.show', $customer) : route('admin.customers.index') }}" class="text-center text-sm font-semibold text-fg-brand hover:underline">{{ __('Cancel') }}</a>
                </div>
            </x-ui.card>
        </aside>
    </form>
</x-layouts.admin>
