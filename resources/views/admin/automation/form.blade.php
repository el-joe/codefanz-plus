@php
    $direction = old('direction', $workflow->direction?->value ?? 'outbound');
    $chosenEvents = old('events', $workflow->events ?? []);
    $secret = session('automation_secret');
    $endpoint = $workflow->exists && ! $workflow->isOutbound() ? route('webhooks.n8n', $workflow->key) : null;
@endphp

<x-layouts.admin :title="$workflow->exists ? $workflow->name : __('New n8n workflow')" :crumb="__('n8n integrations')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('events') ?: $errors->first('acts_as_user_id') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($secret)
        <x-ui.alert tone="warning" :title="__('Copy this signing secret now')">
            <div class="flex flex-col gap-2" data-automation-secret>
                <p>{{ __('It is shown only once. Store it as a credential in n8n, never in a workflow note or repository.') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <code class="max-w-full overflow-x-auto rounded bg-surface px-2 py-1 font-mono text-xs text-fg" dir="ltr">{{ $secret }}</code>
                    <button type="button" data-copy="{{ $secret }}" data-copied-label="{{ __('Copied') }}" class="rounded-md border border-line bg-surface px-2 py-1 text-xs font-semibold text-fg hover:bg-subtle">{{ __('Copy secret') }}</button>
                </div>
            </div>
        </x-ui.alert>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <form id="automation-form" method="POST" action="{{ $workflow->exists ? route('admin.automation.update', $workflow) : route('admin.automation.store') }}" class="flex flex-col gap-6">
            @csrf
            @if ($workflow->exists)
                @method('PUT')
            @endif

            <x-ui.card :title="__('Workflow')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="name" :label="__('Name')" :value="$workflow->name" required maxlength="120" dir="auto" :placeholder="__('New lead → Telegram + email')" />
                    <div class="flex flex-col gap-1.5">
                        <p class="text-sm font-semibold text-fg">{{ __('Direction') }}</p>
                        <input type="hidden" name="direction" value="{{ $direction }}">
                        <p class="py-2.5 text-sm text-fg-secondary">{{ $directions[$direction] ?? $direction }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-fg sm:col-span-2"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $workflow->is_enabled ?? true)) class="size-4 accent-brand"> {{ __('Enabled') }}</label>
                </div>
            </x-ui.card>

            @if ($direction === 'outbound')
                <x-ui.card :title="__('Outbound: send events to n8n')">
                    <div class="flex flex-col gap-4">
                        <x-ui.input name="url" type="url" :label="__('n8n webhook URL')" :value="$workflow->url" required maxlength="500" dir="ltr" placeholder="https://n8n.example.com/webhook/…" :helper="__('HTTPS only. Each delivery is signed with this workflow\'s secret and retried with backoff when n8n does not answer 2xx.')" />
                        <fieldset>
                            <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Events') }}</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($events as $value => $label)
                                    <label class="flex items-center gap-2 rounded-md border border-line p-3 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle">
                                        <input type="checkbox" name="events[]" value="{{ $value }}" @checked(in_array($value, $chosenEvents, true)) class="size-4 accent-brand">
                                        <span class="flex flex-col"><span>{{ $label }}</span><code class="font-mono text-xs text-fg-tertiary" dir="ltr">{{ $value }}</code></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('events')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </fieldset>
                        <label class="flex items-start gap-3">
                            <input type="hidden" name="include_personal_data" value="0">
                            <input type="checkbox" name="include_personal_data" value="1" @checked(old('include_personal_data', $workflow->include_personal_data)) class="mt-0.5 size-4 accent-brand">
                            <span class="flex flex-col">
                                <span class="text-sm font-semibold text-fg">{{ __('Include personal data') }}</span>
                                <span class="text-xs text-fg-tertiary">{{ __('Off by default: payloads carry references and admin links, not names, emails, phone numbers or ticket subjects.') }}</span>
                            </span>
                        </label>
                    </div>
                </x-ui.card>
            @else
                <x-ui.card :title="__('Inbound: n8n calls CodeFanz')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select name="action" :label="__('Allowed action')" :options="$actions" :value="$workflow->action?->value" :placeholder="__('Choose one action')" />
                        <x-ui.select name="acts_as_user_id" :label="__('Runs as')" :options="$staff->all()" :value="$workflow->acts_as_user_id" :placeholder="__('Choose a staff member')" :helper="__('The action is limited to this person\'s permissions.')" />
                    </div>
                    <p class="mt-4 text-xs text-fg-tertiary">{{ __('Send POST requests with a JSON body and the headers X-CodeFanz-Timestamp (Unix seconds), X-CodeFanz-Delivery (unique id per request) and X-CodeFanz-Signature: sha256=HMAC_SHA256(secret, timestamp + "." + body). Requests older than five minutes, with a bad signature or a reused delivery id are refused.') }}</p>
                </x-ui.card>
            @endif
        </form>

        <aside class="flex flex-col gap-6">
            <x-ui.card :title="__('Save')">
                <div class="flex flex-col gap-3">
                    @unless ($workflow->exists)
                        <p class="text-sm text-fg-secondary">{{ __('A signing secret is generated when you create the workflow and shown once.') }}</p>
                    @endunless
                    <x-ui.button form="automation-form">{{ $workflow->exists ? __('Save changes') : __('Create workflow') }}</x-ui.button>
                    <x-ui.button variant="ghost" :href="route('admin.automation.index')">{{ __('Back to n8n integrations') }}</x-ui.button>
                </div>
            </x-ui.card>

            @if ($workflow->exists)
                <x-ui.card :title="__('Signing')">
                    <div class="flex flex-col gap-3 text-sm">
                        @if ($endpoint)
                            <div>
                                <p class="text-fg-tertiary">{{ __('Endpoint URL') }}</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <code class="min-w-0 flex-1 truncate font-mono text-xs text-fg" dir="ltr">{{ $endpoint }}</code>
                                    <button type="button" data-copy="{{ $endpoint }}" data-copied-label="{{ __('Copied') }}" class="rounded-md border border-line px-2 py-1 text-xs font-semibold text-fg hover:bg-subtle">{{ __('Copy URL') }}</button>
                                </div>
                            </div>
                        @endif
                        <p><span class="text-fg-tertiary">{{ __('Secret') }}:</span> <code class="font-mono text-xs" dir="ltr">{{ $workflow->maskedSecret() }}</code></p>
                        <form method="POST" action="{{ route('admin.automation.rotate', $workflow) }}" data-confirm="{{ __('Rotate the secret? Requests signed with the old one stop working immediately.') }}" data-confirm-label="{{ __('Rotate') }}">
                            @csrf
                            <x-ui.button variant="secondary" size="sm" class="w-full">{{ __('Rotate secret') }}</x-ui.button>
                        </form>
                        @if ($workflow->isOutbound())
                            <form method="POST" action="{{ route('admin.automation.test', $workflow) }}">
                                @csrf
                                <x-ui.button variant="secondary" size="sm" class="w-full">{{ __('Send test event') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>

                @include('admin._trash-card', ['action' => route('admin.automation.destroy', $workflow), 'confirm' => __('Delete :name? Its run log is kept.', ['name' => $workflow->name]), 'label' => __('Delete workflow')])
            @endif
        </aside>
    </div>

    @if ($workflow->exists)
        <x-ui.card padding="p-0" class="overflow-x-auto">
            <h2 class="px-5 pt-5 pb-4 text-base font-semibold text-fg">{{ __('Recent runs') }}</h2>
            @if ($recentRuns->isEmpty())
                <x-ui.empty-state :title="__('No runs yet')" :description="__('Runs of this workflow will appear here.')" />
            @else
                @include('admin.automation._runs', ['runs' => $recentRuns, 'showWorkflow' => false])
            @endif
        </x-ui.card>
    @endif
</x-layouts.admin>
