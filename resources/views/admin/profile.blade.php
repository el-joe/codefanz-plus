@php
    $languages = \App\Models\Language::active()->map(fn ($language) => $language['native_name']);
    $timezones = collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($zone) => [$zone => str_replace('_', ' ', $zone)]);
@endphp

<x-layouts.admin :title="__('Profile')" :crumb="__('Account')">
    @if (session('status') === 'profile-information-updated')
        <x-ui.alert tone="success">{{ __('Profile saved.') }}</x-ui.alert>
    @elseif (session('status') === 'password-updated')
        <x-ui.alert tone="success">{{ __('Password changed. Other devices have been signed out.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 max-w-5xl gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Personal details')">
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <x-ui.input name="name" bag="updateProfileInformation" :label="__('Full name')" :value="$user->name" required autocomplete="name" dir="auto" />
                <x-ui.input name="email" bag="updateProfileInformation" type="email" :label="__('Email')" :value="$user->email" required autocomplete="email" dir="ltr" />
                <x-ui.select name="locale" bag="updateProfileInformation" :label="__('Language')" :value="$user->locale" :options="$languages" :helper="__('Used for the admin, emails and notifications.')" />
                <x-ui.select name="timezone" bag="updateProfileInformation" :label="__('Time zone')" :value="$user->timezone" :options="$timezones" />
                <div><x-ui.button>{{ __('Save changes') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('Password')">
            <form method="POST" action="{{ route('user-password.update') }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <x-ui.input name="current_password" bag="updatePassword" type="password" :label="__('Current password')" required autocomplete="current-password" dir="ltr" />
                <x-ui.input name="password" bag="updatePassword" type="password" :label="__('New password')" required autocomplete="new-password" dir="ltr" />
                <x-ui.input name="password_confirmation" bag="updatePassword" type="password" :label="__('Confirm new password')" required autocomplete="new-password" dir="ltr" />
                <p class="text-xs text-fg-tertiary">{{ __('Changing your password signs you out on every other device.') }}</p>
                <div><x-ui.button>{{ __('Change password') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        @can(\App\Enums\Permission::ContentManage->value)
            <x-ui.card :title="__('Public author profile')" class="lg:col-span-2">
                <form method="POST" action="{{ route('admin.profile.author') }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')
                    <p class="text-sm text-fg-secondary">{{ __('Shown under the blog articles you write. Leave empty to show only your name.') }}</p>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        @foreach (\App\Models\Language::active() as $code => $language)
                            <div class="flex flex-col gap-4" lang="{{ $code }}" dir="{{ $language['direction'] }}">
                                <p class="text-sm font-semibold text-fg-tertiary">{{ $language['native_name'] }}</p>
                                <x-ui.input :name="'author_title['.$code.']'" :id="'author-title-'.$code" :label="__('Job title')" :value="($user->author_title ?? [])[$code] ?? null" maxlength="100" />
                                @include('admin._textarea', ['name' => "author_bio[{$code}]", 'id' => "author-bio-{$code}", 'label' => __('Short bio'), 'value' => ($user->author_bio ?? [])[$code] ?? null, 'rows' => 3, 'max' => 600])
                            </div>
                        @endforeach
                    </div>
                    <div><x-ui.button>{{ __('Save author profile') }}</x-ui.button></div>
                </form>
            </x-ui.card>
        @endcan
    </div>
</x-layouts.admin>
