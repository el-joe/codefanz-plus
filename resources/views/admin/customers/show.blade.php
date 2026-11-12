@php
    $canManage = auth()->user()->can('update', $customer);
    $languages = \App\Models\Language::active();
    $inviteErrors = $errors->getBag('invitePortalUser');
@endphp

<x-layouts.admin :title="$customer->company_name" :crumb="__('Customers')">
    <div class="flex flex-wrap items-center gap-3">
        <span class="font-mono text-sm text-fg-tertiary" dir="ltr">{{ $customer->reference }}</span>
        <x-ui.badge :tone="$customer->status->tone()">{{ $customer->status->label() }}</x-ui.badge>
        <x-ui.badge tone="neutral" :dot="false">{{ $customer->type->label() }}</x-ui.badge>
        <div class="flex-1"></div>
        <a href="{{ route('admin.customers.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to customers') }}</a>
        @if ($canManage)
            <x-ui.button variant="secondary" size="sm" :href="route('admin.customers.edit', $customer)">{{ __('Edit details') }}</x-ui.button>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Portal users')">
                <p class="mb-4 text-sm text-fg-secondary">{{ __('People who can sign in to the client portal for this customer. They only ever see this customer’s records.') }}</p>

                @if ($customer->users->isEmpty())
                    <x-ui.empty-state :title="__('No portal users yet')" :description="__('Invite the customer’s contact so they can follow projects and payments.')" class="py-6" />
                @else
                    <ul class="flex flex-col divide-y divide-line-subtle" data-portal-users>
                        @foreach ($customer->users as $portalUser)
                            @php
                                $revoked = $portalUser->pivot->revoked_at !== null;
                                [$statusTone, $statusLabel] = match (true) {
                                    $revoked => ['danger', __('Access off')],
                                    ! $portalUser->is_active => ['danger', __('Deactivated')],
                                    $portalUser->last_login_at === null => ['warning', __('Invited')],
                                    default => ['success', __('Active')],
                                };
                            @endphp
                            <li class="flex flex-wrap items-center gap-3 py-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-xs font-semibold text-fg-brand" aria-hidden="true">{{ $portalUser->initials() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-fg" dir="auto">{{ $portalUser->name }}</p>
                                    <p class="truncate text-xs text-fg-tertiary"><span dir="ltr">{{ $portalUser->email }}</span> · {{ $portalUser->pivot->role->label() }} · {{ $portalUser->last_login_at ? __('Last sign-in :time', ['time' => $portalUser->last_login_at->diffForHumans()]) : __('Never signed in') }}</p>
                                </div>
                                <x-ui.badge :tone="$statusTone">{{ $statusLabel }}</x-ui.badge>
                                @can('managePortalAccess', $customer)
                                    <div class="flex flex-wrap gap-2">
                                        @if ($revoked)
                                            <form method="POST" action="{{ route('admin.customers.portal-users.restore', [$customer, $portalUser]) }}">
                                                @csrf
                                                <x-ui.button variant="secondary" size="sm">{{ __('Restore access') }}</x-ui.button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.customers.portal-users.invitation', [$customer, $portalUser]) }}">
                                                @csrf
                                                <x-ui.button variant="secondary" size="sm">{{ __('Resend invitation') }}</x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.customers.portal-users.revoke', [$customer, $portalUser]) }}"
                                                  data-confirm="{{ __('Switch off portal access for :name? They are signed out if this was their only customer.', ['name' => $portalUser->name]) }}" data-confirm-label="{{ __('Switch off') }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.button variant="danger" size="sm">{{ __('Switch off access') }}</x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('managePortalAccess', $customer)
                    <form method="POST" action="{{ route('admin.customers.portal-users.store', $customer) }}" class="mt-6 flex flex-col gap-4 rounded-lg border border-line p-4" data-invite-portal-user>
                        @csrf
                        <h3 class="text-base font-semibold text-fg">{{ __('Invite a portal user') }}</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input name="name" bag="invitePortalUser" id="invite-name" :label="__('Full name')" :value="$customer->users->isEmpty() ? $customer->contact_name : null" required maxlength="120" dir="auto" />
                            <x-ui.input name="email" bag="invitePortalUser" id="invite-email" type="email" :label="__('Email')" :value="$customer->users->isEmpty() ? $customer->email : null" required maxlength="255" dir="ltr" />
                            <x-ui.select name="locale" bag="invitePortalUser" id="invite-locale" :label="__('Language')" :options="$languages->map(fn ($language) => $language['native_name'])" :value="$customer->preferred_locale" :helper="__('The invitation email is sent in this language.')" />
                            <x-ui.select name="role" bag="invitePortalUser" id="invite-role" :label="__('Role in account')" :options="\App\Enums\CustomerUserRole::options()" :value="$customer->users->isEmpty() ? 'owner' : 'member'" />
                        </div>
                        <x-ui.button size="sm" class="w-fit">{{ __('Send invitation') }}</x-ui.button>
                    </form>
                @endcan
            </x-ui.card>

            @if (filled($customer->notes))
                <x-ui.card :title="__('Internal notes')">
                    <p class="whitespace-pre-line text-sm text-fg" dir="auto">{{ $customer->notes }}</p>
                </x-ui.card>
            @endif
        </div>

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Contact')">
                <dl class="flex flex-col divide-y divide-line-subtle text-sm">
                    @foreach (array_filter([
                        __('Primary contact') => $customer->contact_name,
                        __('Email') => $customer->email,
                        __('Phone') => $customer->phone,
                        __('Country') => $countryName,
                        __('Preferred language') => $languages[$customer->preferred_locale]['native_name'] ?? strtoupper($customer->preferred_locale),
                        __('Tax ID') => $customer->tax_id,
                        __('Created') => $customer->created_at->translatedFormat('j M Y, H:i'),
                        __('Added by') => $customer->creator?->name,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-fg-tertiary">{{ $label }}</dt>
                            <dd class="text-end font-semibold text-fg break-all" @if (in_array($label, [__('Email'), __('Phone'), __('Tax ID')], true)) dir="ltr" @else dir="auto" @endif>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Billing address')">
                @if ($customer->billingAddressLines())
                    <address class="text-sm text-fg not-italic" dir="auto">
                        @foreach ($customer->billingAddressLines() as $line)
                            {{ $line }}<br>
                        @endforeach
                        {{ $countryName }}
                    </address>
                @else
                    <p class="text-sm text-fg-secondary">{{ __('No billing address yet.') }}</p>
                @endif
            </x-ui.card>

            @if ($customer->lead)
                <x-ui.card :title="__('Source lead')">
                    @can('view', $customer->lead)
                        <a href="{{ route('admin.leads.show', $customer->lead) }}" class="text-sm font-semibold text-fg-brand hover:underline"><span dir="ltr">{{ $customer->lead->reference }}</span> · <span dir="auto">{{ $customer->lead->name }}</span></a>
                    @else
                        <p class="text-sm text-fg"><span dir="ltr">{{ $customer->lead->reference }}</span></p>
                    @endcan
                </x-ui.card>
            @endif

            @can('delete', $customer)
                <x-ui.card :title="__('Delete customer')">
                    <p class="mb-3 text-sm text-fg-secondary">{{ __('Switch off portal access for every user first. Deleted customers can be restored from the database by an administrator.') }}</p>
                    <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" data-confirm="{{ __('Delete :name?', ['name' => $customer->company_name]) }}" data-confirm-label="{{ __('Delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="danger" size="sm">{{ __('Delete customer') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan
        </aside>
    </div>
</x-layouts.admin>
