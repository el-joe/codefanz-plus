@php
    $assignedRoles = old('roles', $user->exists ? $user->roles->pluck('name')->all() : []);
    $isSelf = $user->is(auth()->user());
    $languages = \App\Models\Language::active()->map(fn ($language) => $language['native_name']);
    $timezones = collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($zone) => [$zone => str_replace('_', ' ', $zone)]);
@endphp

<x-layouts.admin :title="$user->exists ? $user->name : __('Invite user')" :crumb="__('Users & roles')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('roles') ?: $errors->first('is_active') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="user-form" method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="flex flex-col gap-6">
            @csrf
            @if ($user->exists)
                @method('PUT')
            @endif

            <x-ui.card :title="__('Details')">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" :label="__('Full name')" :value="$user->name" required autocomplete="off" dir="auto" />
                    <x-ui.input name="email" type="email" :label="__('Email')" :value="$user->email" required autocomplete="off" dir="ltr" />
                    <x-ui.select name="locale" :label="__('Language')" :value="$user->locale" :options="$languages" :helper="__('Used for emails and notifications.')" />
                    <x-ui.select name="timezone" :label="__('Time zone')" :value="$user->timezone" :options="$timezones" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Roles')">
                <fieldset>
                    <legend class="mb-3 text-sm text-fg-secondary">{{ __('Permissions are combined across all assigned roles and checked on the server for every action.') }}</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach (\App\Enums\Role::staff() as $role)
                            <label class="flex items-start gap-3 rounded-md border border-line p-3 has-checked:border-line-brand has-checked:bg-brand-subtle">
                                <input type="checkbox" name="roles[]" value="{{ $role->value }}" @checked(in_array($role->value, $assignedRoles, true))
                                       class="mt-0.5 size-4 accent-brand">
                                <span class="flex flex-col">
                                    <span class="text-sm font-semibold text-fg">{{ $role->label() }}</span>
                                    @if ($role->requiresTwoFactor())
                                        <span class="text-xs text-fg-tertiary">{{ __('Requires two-factor authentication') }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('roles')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
                </fieldset>
            </x-ui.card>
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Account')">
                <div class="flex flex-col gap-4">
                    @if ($user->exists)
                        <label class="flex items-center justify-between gap-3">
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold text-fg">{{ __('Active') }}</span>
                                <span class="text-xs text-fg-tertiary">{{ __('Deactivating signs the user out everywhere.') }}</span>
                            </span>
                            <input type="hidden" name="is_active" value="0" form="user-form">
                            <input type="checkbox" name="is_active" value="1" form="user-form" @checked(old('is_active', $user->is_active)) @disabled($isSelf) class="size-5 accent-brand">
                        </label>
                        @if ($isSelf)
                            <input type="hidden" name="is_active" value="1" form="user-form">
                        @endif
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <dt class="text-fg-tertiary">{{ __('Two-factor') }}</dt>
                            <dd class="text-fg">{{ $user->hasConfirmedTwoFactor() ? __('Enabled') : __('Off') }}</dd>
                            <dt class="text-fg-tertiary">{{ __('Last sign-in') }}</dt>
                            <dd class="text-fg">{{ $user->last_login_at?->diffForHumans() ?? __('Never') }}</dd>
                        </dl>
                    @else
                        <p class="text-sm text-fg-secondary">{{ __('The user receives an email in their language with a link to choose a password.') }}</p>
                    @endif
                    <x-ui.button form="user-form">{{ $user->exists ? __('Save changes') : __('Send invitation') }}</x-ui.button>
                </div>
            </x-ui.card>

            @if ($user->exists)
                <x-ui.card :title="__('Access recovery')">
                    <div class="flex flex-col gap-3">
                        <form method="POST" action="{{ route('admin.users.invitation', $user) }}">
                            @csrf
                            <x-ui.button variant="secondary" size="sm" class="w-full">{{ __('Send password link') }}</x-ui.button>
                        </form>
                        @can('resetTwoFactor', $user)
                            @if ($user->two_factor_secret && ! $isSelf)
                                <form method="POST" action="{{ route('admin.users.two-factor.destroy', $user) }}"
                                      data-confirm="{{ __('Reset two-factor for :name? They will need to set it up again.', ['name' => $user->name]) }}" data-confirm-label="{{ __('Reset') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="danger" size="sm" class="w-full">{{ __('Reset two-factor') }}</x-ui.button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
