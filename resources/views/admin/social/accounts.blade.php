{{-- B06/B11 social accounts: one card per OAuth app with an honest status (requires credentials / needs app approval /
     connected) and its redirect URI, the connected and manual accounts, and manual accounts for TikTok and YouTube. --}}
@php
    use App\Enums\SocialPlatform;

    $timezone = auth()->user()->timezone ?: config('app.timezone');
@endphp

<x-layouts.admin :title="__('Social accounts')" :crumb="__('Social')">
    @include('admin.social._tabs')

    <p class="max-w-3xl text-sm text-fg-secondary">{{ __('Publishing uses each platform’s official API. Where direct publishing isn’t permitted — the app is still in review, the plan has no write access, or the platform offers no API — approved posts become a ready-to-post package. Nothing is ever reported as published without a confirmation.') }}</p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach ($apps as $provider => $app)
            <x-ui.card data-social-app="{{ $provider }}">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold text-fg">{{ $app['label'] }}</p>
                    <x-ui.badge :tone="$app['status']->tone()" data-app-status="{{ $app['status']->value }}">{{ $app['status']->label() }}</x-ui.badge>
                </div>
                <p class="mt-2 text-xs text-fg-tertiary">{{ collect($app['platforms'])->map(fn ($platform) => $platform->label())->implode(' · ') }}</p>
                <p class="mt-2 text-sm text-fg-secondary">{{ $app['status']->description() }}</p>
                @if (! empty($app['outcome']['message']))
                    <p class="mt-2 text-xs text-fg-warning" dir="auto">{{ $app['outcome']['message'] }}</p>
                @endif
                <div class="mt-3 flex flex-col gap-1 text-xs">
                    <span class="font-semibold text-fg">{{ __('Redirect URI to register in the app') }}</span>
                    <span class="flex items-center gap-2">
                        <code class="min-w-0 flex-1 truncate rounded bg-subtle px-2 py-1" dir="ltr">{{ $app['redirect'] }}</code>
                        <button type="button" data-copy="{{ $app['redirect'] }}" data-copied-label="{{ __('Copied') }}" class="font-semibold text-fg-brand hover:underline">{{ __('Copy') }}</button>
                    </span>
                    <span class="text-fg-tertiary">{{ __('Permissions requested') }}: <span dir="ltr">{{ implode(', ', $app['scopes']) }}</span></span>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($app['configured'])
                        <x-ui.button size="sm" :href="route('admin.social.accounts.connect', $provider)">{{ __('Connect') }}</x-ui.button>
                    @endif
                    @can('settings.manage')
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.settings.edit', 'social')">{{ __('App credentials') }}</x-ui.button>
                    @endcan
                </div>
            </x-ui.card>
        @endforeach
        <x-ui.card data-social-app="manual">
            <div class="flex items-center justify-between gap-3">
                <p class="font-semibold text-fg">{{ __('TikTok · YouTube') }}</p>
                <x-ui.badge>{{ __('Manual export') }}</x-ui.badge>
            </div>
            <p class="mt-2 text-sm text-fg-secondary">{{ __('Both need an audited app and video uploads, so posts for them are always prepared for manual export.') }}</p>
        </x-ui.card>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($accounts->isEmpty())
            <x-ui.empty-state :title="__('No social accounts yet')" :description="__('Connect an app above, or add a manual account below.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Account') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Token') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Last verified') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        <tr class="border-t border-line-subtle align-top" data-social-account="{{ $account->id }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-fg" dir="auto">{{ $account->displayName() }}</p>
                                @if ($account->profile_url)
                                    <a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-fg-brand hover:underline" dir="ltr">{{ \Illuminate\Support\Str::limit($account->profile_url, 50) }}</a>
                                @endif
                                @if ($account->last_error)
                                    <p class="mt-1 text-xs text-fg-danger" dir="auto">{{ $account->last_error }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge :tone="$account->status->tone()" data-account-status="{{ $account->status->value }}">{{ $account->status->label() }}</x-ui.badge>
                                <p class="mt-1 max-w-xs text-xs text-fg-tertiary">{{ $account->status->description() }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-fg-secondary">
                                @if (filled($account->access_token))
                                    {{ __('Encrypted') }}
                                    @if ($account->token_expires_at)
                                        · {{ __('expires :date', ['date' => $account->token_expires_at->timezone($timezone)->translatedFormat('j M Y H:i')]) }}
                                    @else
                                        · {{ __('no expiry') }}
                                    @endif
                                    @if (filled($account->refresh_token))· {{ __('auto-refresh') }}@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-fg-secondary">{{ $account->last_verified_at?->timezone($timezone)->translatedFormat('j M Y H:i') ?? __('Not verified') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if (filled($account->access_token))
                                        <form method="POST" action="{{ route('admin.social.accounts.verify', $account) }}">
                                            @csrf
                                            <x-ui.button size="sm" variant="secondary">{{ __('Verify') }}</x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.social.accounts.manual', $account) }}" data-confirm="{{ __('Stop publishing through the API and remove the tokens? Posts for this account will be prepared for manual export.') }}">
                                            @csrf
                                            <x-ui.button size="sm" variant="ghost">{{ __('Use manual export') }}</x-ui.button>
                                        </form>
                                    @elseif ($account->platform->provider() && in_array($account->platform->provider(), array_keys(array_filter($apps, fn ($app) => $app['configured'])), true))
                                        <x-ui.button size="sm" variant="secondary" :href="route('admin.social.accounts.connect', $account->platform->provider())">{{ __('Reconnect') }}</x-ui.button>
                                    @endif
                                    @if ($account->status !== \App\Enums\SocialAccountStatus::Disconnected)
                                        <form method="POST" action="{{ route('admin.social.accounts.destroy', $account) }}" data-confirm="{{ __('Disconnect this account? Its tokens are deleted; past posts keep their history.') }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button size="sm" variant="ghost" class="text-fg-danger">{{ __('Disconnect') }}</x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Add a manual account')">
        <p class="mb-4 text-sm text-fg-secondary">{{ __('For TikTok, YouTube, or any platform while its app waits for approval. Approved posts for it are prepared as a copy-ready package.') }}</p>
        <form method="POST" action="{{ route('admin.social.accounts.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-[200px_1fr_1fr_auto] md:items-end">
            @csrf
            <x-ui.select name="platform" :label="__('Platform')" :options="collect(SocialPlatform::cases())->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()])->all()" />
            <x-ui.input name="name" :label="__('Account name')" required maxlength="150" />
            <x-ui.input name="profile_url" type="url" :label="__('Profile link (optional)')" maxlength="500" dir="ltr" />
            <x-ui.button>{{ __('Add account') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layouts.admin>
