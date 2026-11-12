{{-- Create payment link dialog (A04). Milestone links are capped at the remaining balance (checked again on the server). --}}
@php
    $bag = $errors->getBag('paymentLink');
    $gatewayStatusLabels = [
        'requires_credentials' => __('Requires credentials'),
        'incomplete' => __('Incomplete'),
        'sandbox' => __('Sandbox'),
        'live_unverified' => __('Live · not verified'),
        'connected' => __('Connected'),
    ];
    $defaultGateways = array_keys(array_filter($linkDialog['gateways'], fn ($gateway) => $gateway['configured'])) ?: array_slice(array_keys($linkDialog['gateways']), 0, 1);
    $milestoneOptions = $project->milestones->filter(fn ($milestone) => ($linkDialog['remaining'][$milestone->ulid] ?? 0) > 0);
    $defaultExpiry = now()->addDays((int) config('codefanz.payments.link_default_days', 14))->toDateString();
@endphp
<dialog id="payment-link-dialog" data-payment-link-dialog @if ($bag->any()) data-dialog-autoopen @endif
        aria-labelledby="payment-link-title"
        class="m-auto w-full max-w-lg rounded-2xl bg-surface-raised p-6 text-fg shadow-xl backdrop:bg-ink-950/50">
    <form method="POST" action="{{ route('admin.projects.payment-links.store', $project) }}" class="flex flex-col gap-4" data-payment-link-form>
        @csrf
        <div class="flex items-start gap-3">
            <div class="flex-1">
                <h2 id="payment-link-title" class="text-xl font-semibold">{{ __('Create payment link') }}</h2>
                <p class="text-sm text-fg-secondary" dir="auto">{{ $project->name }} · <span dir="ltr">{{ $project->reference }}</span></p>
            </div>
            <button type="button" data-dialog-close class="rounded-md p-1 text-fg-secondary hover:bg-subtle" aria-label="{{ __('Close') }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        @if ($bag->any())
            <x-ui.alert tone="danger">{{ $bag->first() }}</x-ui.alert>
        @endif

        <div class="flex flex-col gap-1.5">
            <label for="link-milestone" class="text-sm font-semibold">{{ __('Milestone') }}</label>
            <select id="link-milestone" name="milestone" data-link-milestone class="w-full rounded-md border border-line bg-surface px-3 py-2.5 text-base text-fg">
                <option value="" data-currency="{{ $project->currency }}">{{ __('Custom amount (no milestone)') }}</option>
                @foreach ($milestoneOptions as $milestone)
                    @php($remaining = $linkDialog['remaining'][$milestone->ulid])
                    <option value="{{ $milestone->ulid }}" data-remaining="{{ \App\Services\Money::toDecimal($remaining) }}" data-currency="{{ $milestone->currency }}"
                            data-max-label="{{ __('Max :amount (remaining on milestone)', ['amount' => \App\Services\Money::plain($remaining, $milestone->currency)]) }}"
                            @selected(old('milestone') === $milestone->ulid)>{{ $milestone->title }} — {{ __(':amount remaining', ['amount' => \App\Services\Money::plain($remaining, $milestone->currency)]) }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-[1fr_8rem] gap-3">
            <x-ui.input name="amount" id="link-amount" type="number" step="0.01" min="0.01" :label="__('Amount')" bag="paymentLink" required dir="ltr" data-link-amount />
            <x-ui.select name="currency" id="link-currency" :label="__('Currency')" bag="paymentLink" :options="array_combine(config('codefanz.payments.currencies'), config('codefanz.payments.currencies'))" :value="in_array($project->currency, config('codefanz.payments.currencies'), true) ? $project->currency : 'USD'" data-link-currency />
        </div>
        <p class="-mt-2 text-xs text-fg-tertiary" data-link-max aria-live="polite"></p>

        <x-ui.input name="description" id="link-description" :label="__('Description (shown to client)')" bag="paymentLink" maxlength="255" dir="auto" :placeholder="__('e.g. Second installment (50%)')" />

        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.input name="expires_on" id="link-expires" type="date" :label="__('Expires on')" bag="paymentLink" :value="$defaultExpiry" :min="now()->toDateString()" />
            <x-ui.select name="usage" id="link-usage" :label="__('Usage')" bag="paymentLink" :options="['single' => __('Single use'), 'reusable' => __('Reusable')]" value="single" data-link-usage />
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-semibold">{{ __('Allowed gateways') }}</legend>
            <div class="flex flex-col gap-2">
                @foreach ($linkDialog['gateways'] as $key => $gateway)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="gateways[]" value="{{ $key }}" class="size-4 accent-brand" @checked(in_array($key, old('gateways', $defaultGateways), true))>
                        <span class="font-semibold">{{ $gateway['label'] }}</span>
                        <x-ui.badge :tone="$gateway['configured'] ? (in_array($gateway['status'], ['sandbox', 'connected'], true) ? ($gateway['status'] === 'connected' ? 'success' : 'info') : 'warning') : 'neutral'" :dot="false">{{ $gatewayStatusLabels[$gateway['status']] }}</x-ui.badge>
                        <span class="text-xs text-fg-tertiary" dir="ltr" data-gateway-currencies="{{ $key }}">{{ implode(' · ', $gateway['currencies']) }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-fg-tertiary">{{ __('Only gateways with saved credentials are offered to the client. Each gateway accepts only the currencies listed next to it.') }}</p>
        </fieldset>

        <x-ui.alert tone="info" :title="__('Secure by design')">
            {{ __('Clients can’t change the amount. A payment is marked paid only after a webhook signature check and verification with the gateway.') }}
        </x-ui.alert>

        <div class="flex justify-end gap-3">
            <x-ui.button variant="secondary" type="button" data-dialog-close>{{ __('Cancel') }}</x-ui.button>
            <x-ui.button>{{ __('Create link') }}</x-ui.button>
        </div>
    </form>
</dialog>
