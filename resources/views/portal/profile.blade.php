@php
    $languages = \App\Models\Language::active();
    $timezones = collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($zone) => [$zone => str_replace('_', ' ', $zone)]);
    $selectedLocale = old('locale', $user->locale);
@endphp

<x-layouts.portal :title="__('Profile & Settings')" :crumb="__('Profile & Settings')">
    @include('portal._settings-tabs')

    @if (session('status') === 'profile-information-updated')
        <x-ui.alert tone="success">{{ __('Profile saved.') }}</x-ui.alert>
    @elseif (session('status') === 'password-updated')
        <x-ui.alert tone="success">{{ __('Password changed. Other devices have been signed out.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <x-ui.card>
            <div class="mb-5 flex items-center gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-subtle text-lg font-semibold text-fg-brand" aria-hidden="true">{{ $user->initials() }}</span>
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold text-fg" dir="auto">{{ $user->name }}</p>
                    <p class="truncate text-sm text-fg-secondary" dir="auto">{{ $membership?->role?->label() }} · {{ $customer->company_name }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('user-profile-information.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" bag="updateProfileInformation" :label="__('Full name')" :value="$user->name" required autocomplete="name" dir="auto" />
                    <x-ui.input name="email" bag="updateProfileInformation" type="email" :label="__('Email')" :value="$user->email" required autocomplete="email" dir="ltr" />
                </div>
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Portal language') }}</legend>
                    <div class="flex flex-wrap gap-5">
                        @foreach ($languages as $code => $language)
                            <label class="flex items-center gap-2 text-base text-fg">
                                <input type="radio" name="locale" value="{{ $code }}" @checked($selectedLocale === $code) class="size-4 accent-brand">
                                <span lang="{{ $code }}">{{ $language['native_name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-sm text-fg-secondary">{{ __('Used for the portal, emails and notifications.') }}</p>
                    @if ($errors->updateProfileInformation->has('locale'))
                        <p class="mt-1 text-sm text-fg-danger">{{ $errors->updateProfileInformation->first('locale') }}</p>
                    @endif
                </fieldset>
                <x-ui.select name="timezone" bag="updateProfileInformation" :label="__('Time zone')" :value="$user->timezone ?: config('app.timezone')" :options="$timezones" />
                <div><x-ui.button>{{ __('Save changes') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Password')">
                <form method="POST" action="{{ route('user-password.update') }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')
                    <x-ui.input name="current_password" bag="updatePassword" type="password" :label="__('Current password')" required autocomplete="current-password" dir="ltr" />
                    <x-ui.input name="password" bag="updatePassword" type="password" :label="__('New password')" required autocomplete="new-password" dir="ltr" />
                    <x-ui.input name="password_confirmation" bag="updatePassword" type="password" :label="__('Confirm new password')" required autocomplete="new-password" dir="ltr" />
                    <p class="text-xs text-fg-tertiary">{{ __('Changing your password signs you out on every other device.') }}</p>
                    <div><x-ui.button variant="secondary">{{ __('Change password') }}</x-ui.button></div>
                </form>
            </x-ui.card>

            <x-ui.card :title="__('Your account')">
                <dl class="flex flex-col divide-y divide-line-subtle text-sm">
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-fg-tertiary">{{ __('Customer') }}</dt>
                        <dd class="text-end font-semibold text-fg" dir="auto">{{ $customer->company_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-fg-tertiary">{{ __('Customer reference') }}</dt>
                        <dd class="font-mono font-semibold text-fg" dir="ltr">{{ $customer->reference }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-fg-tertiary">{{ __('Your role') }}</dt>
                        <dd class="font-semibold text-fg">{{ $membership?->role?->label() }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-fg-tertiary">{{ __('To change company or billing details, contact your CodeFanz team.') }}</p>
            </x-ui.card>
        </div>
    </div>
</x-layouts.portal>
