@php
    $chosenScopes = old('scopes', $client->scopes ?? []);
    $allowedIps = old('allowed_ips', implode("\n", $client->allowed_ips ?? []));
    $plainToken = session('mcp_token');
@endphp

<x-layouts.admin :title="$client->exists ? $client->name : __('New MCP client')" :crumb="__('MCP')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('scopes') ?: $errors->first('allowed_ips') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($plainToken)
        <x-ui.alert tone="warning" :title="__('Copy this token now')">
            <div class="flex flex-col gap-2" data-mcp-token>
                <p>{{ __('It is shown only once and stored only as a hash. Treat it like a password: put it in the MCP client\'s environment, never in a repository.') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <code class="max-w-full overflow-x-auto rounded bg-surface px-2 py-1 font-mono text-xs text-fg" dir="ltr">{{ $plainToken }}</code>
                    <button type="button" data-copy="{{ $plainToken }}" data-copied-label="{{ __('Copied') }}" class="rounded-md border border-line bg-surface px-2 py-1 text-xs font-semibold text-fg hover:bg-subtle">{{ __('Copy token') }}</button>
                </div>
            </div>
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form id="mcp-client-form" method="POST" action="{{ $client->exists ? route('admin.mcp.clients.update', $client) : route('admin.mcp.clients.store') }}" class="flex flex-col gap-6">
            @csrf
            @if ($client->exists)
                @method('PUT')
            @endif

            <x-ui.card :title="__('Client')">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" :label="__('Name')" :value="$client->name" required maxlength="80" dir="ltr" placeholder="claude-desktop" :helper="__('Letters, numbers, spaces, dots, hyphens and underscores.')" />
                    <x-ui.select name="owner_id" :label="__('Owner')" :options="$owners->all()" :value="$client->owner_id" :helper="__('The client can never do more than this person.')" />
                    <x-ui.select name="status" :label="__('Status')" :options="$statuses" :value="$client->status?->value ?? 'active'" />
                    <x-ui.input name="expires_at" type="date" :label="__('Expires on')" :value="$client->expires_at?->format('Y-m-d')" :helper="__('Leave empty for no expiry. Short-lived clients are safer.')" />
                    <div class="sm:col-span-2">
                        <x-ui.input name="description" :label="__('Description')" :value="$client->description" maxlength="255" dir="auto" />
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Scopes')">
                <fieldset>
                    <legend class="mb-3 text-sm text-fg-secondary">{{ __('Least privilege: choose only what this client needs. Write scopes create drafts or pending proposals that a human must approve.') }}</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($scopes as $scope)
                            <label class="flex items-start gap-3 rounded-md border border-line p-3 has-checked:border-line-brand has-checked:bg-brand-subtle">
                                <input type="checkbox" name="scopes[]" value="{{ $scope->value }}" @checked(in_array($scope->value, $chosenScopes, true)) class="mt-0.5 size-4 accent-brand">
                                <span class="flex flex-col gap-0.5">
                                    <span class="text-sm font-semibold text-fg">{{ $scope->label() }}</span>
                                    <span class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $scope->value }}</span>
                                    @if ($scope->isWrite())
                                        <x-ui.badge tone="warning" class="mt-1 self-start">{{ __('Write') }}</x-ui.badge>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('scopes')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
                </fieldset>
            </x-ui.card>

            <x-ui.card :title="__('Limits & privacy')">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.input name="rate_limit_per_minute" type="number" min="1" :max="config('codefanz.mcp.max_rate_limit_per_minute')" :label="__('Rate limit (calls per minute)')" :value="$client->rate_limit_per_minute ?? 60" required />
                    @include('admin._textarea', ['name' => 'allowed_ips', 'id' => 'allowed_ips', 'label' => __('IP allow-list'), 'value' => $allowedIps, 'rows' => 3, 'mono' => true, 'help' => __('One IP address or CIDR range per line (e.g. 203.0.113.10 or 10.0.0.0/8). Leave empty to allow any address.')])
                    <label class="flex items-start gap-3 sm:col-span-2">
                        <input type="hidden" name="allow_lead_pii" value="0">
                        <input type="checkbox" name="allow_lead_pii" value="1" @checked(old('allow_lead_pii', $client->allow_lead_pii)) class="mt-0.5 size-4 accent-brand">
                        <span class="flex flex-col">
                            <span class="text-sm font-semibold text-fg">{{ __('Include lead personal data') }}</span>
                            <span class="text-xs text-fg-tertiary">{{ __('Off by default: lead tools return no names, emails, phone numbers or free text.') }}</span>
                        </span>
                    </label>
                </div>
            </x-ui.card>
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Save')">
                <div class="flex flex-col gap-3">
                    @unless ($client->exists)
                        <p class="text-sm text-fg-secondary">{{ __('A token is issued when you create the client and shown once.') }}</p>
                    @endunless
                    <x-ui.button form="mcp-client-form">{{ $client->exists ? __('Save changes') : __('Create client') }}</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('admin.mcp.index')">{{ __('Back to MCP') }}</x-ui.button>
                </div>
            </x-ui.card>

            @if ($client->exists)
                <x-ui.card :title="__('Token')">
                    <div class="flex flex-col gap-3">
                        @if ($token ?? null)
                            <dl class="grid grid-cols-2 gap-2 text-sm">
                                <dt class="text-fg-tertiary">{{ __('Issued') }}</dt>
                                <dd class="text-fg">{{ $client->token_issued_at?->diffForHumans() ?? '—' }}</dd>
                                <dt class="text-fg-tertiary">{{ __('Last used') }}</dt>
                                <dd class="text-fg">{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</dd>
                                <dt class="text-fg-tertiary">{{ __('Expires') }}</dt>
                                <dd class="text-fg">{{ $token->expires_at?->translatedFormat('M j, Y') ?? __('Never') }}</dd>
                            </dl>
                        @else
                            <p class="text-sm text-fg-secondary">{{ __('No active token: the client cannot connect.') }}</p>
                        @endif
                        <form method="POST" action="{{ route('admin.mcp.clients.token.store', $client) }}"
                              @if ($token ?? null) data-confirm="{{ __('Rotate the token? The current token stops working immediately.') }}" data-confirm-label="{{ __('Rotate') }}" @endif>
                            @csrf
                            <x-ui.button variant="secondary" size="sm" class="w-full">{{ ($token ?? null) ? __('Rotate token') : __('Issue token') }}</x-ui.button>
                        </form>
                        @if ($token ?? null)
                            <form method="POST" action="{{ route('admin.mcp.clients.token.destroy', $client) }}" data-confirm="{{ __('Revoke the token? The client can no longer connect.') }}" data-confirm-label="{{ __('Revoke') }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="danger" size="sm" class="w-full">{{ __('Revoke token') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>

                @include('admin._trash-card', ['action' => route('admin.mcp.clients.destroy', $client), 'confirm' => __('Delete :name and revoke its token?', ['name' => $client->name]), 'label' => __('Delete client')])
            @endif
        </div>
    </div>

    @if ($client->exists)
        <x-ui.card padding="p-0" class="overflow-x-auto">
            <h2 class="px-5 pt-5 pb-4 text-base font-semibold text-fg">{{ __('Recent tool calls') }}</h2>
            @if ($recentCalls->isEmpty())
                <x-ui.empty-state :title="__('No tool calls yet')" :description="__('Calls from this client will appear here.')" />
            @else
                @include('admin.mcp._calls', ['calls' => $recentCalls, 'showClient' => false])
            @endif
        </x-ui.card>
    @endif
</x-layouts.admin>
