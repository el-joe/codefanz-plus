@php($billTo = $invoice->billTo())
<x-layouts.admin :title="__('Invoice :number', ['number' => $invoice->displayNumber()])" :crumb="__('Invoices')">
    @if ($errors->has('invoice') || $errors->has('reason'))
        <x-ui.alert tone="danger">{{ $errors->first('invoice') ?: $errors->first('reason') }}</x-ui.alert>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <p class="font-mono text-lg font-semibold text-fg" dir="ltr">{{ $invoice->displayNumber() }}</p>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-fg-secondary">
                    <x-ui.badge :tone="$invoice->isOverdue() ? 'danger' : $invoice->status->tone()">{{ $invoice->isOverdue() ? __('Overdue') : $invoice->status->label() }}</x-ui.badge>
                    <span dir="auto">{{ $billTo['name'] }}</span>
                    @if ($invoice->project)<span dir="auto">· {{ $invoice->project->name }}</span>@endif
                </p>
            </div>
            <a href="{{ route('admin.invoices.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Back to invoices') }}</a>
            <x-ui.button size="sm" variant="secondary" :href="route('admin.invoices.print', $invoice)">{{ __('Print') }}</x-ui.button>
            <x-ui.button size="sm" variant="secondary" :href="route('admin.invoices.print', ['invoice' => $invoice, 'lang' => app()->getLocale() === 'ar' ? 'en' : 'ar'])">{{ app()->getLocale() === 'ar' ? __('Print in English') : __('Print in Arabic') }}</x-ui.button>
        </div>

        <dl class="mt-6 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-fg-tertiary">{{ __('Total') }}</dt><dd class="mt-0.5 text-lg font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($invoice->total_minor, $invoice->currency) }}</dd></div>
            <div><dt class="text-fg-tertiary">{{ __('Issued') }}</dt><dd class="mt-0.5 font-semibold text-fg">{{ $invoice->issued_at?->translatedFormat('j M Y') ?? '—' }}</dd></div>
            <div><dt class="text-fg-tertiary">{{ __('Due') }}</dt><dd class="mt-0.5 font-semibold text-fg">{{ $invoice->due_at?->translatedFormat('j M Y') ?? '—' }}</dd></div>
            <div><dt class="text-fg-tertiary">{{ __('Payment link') }}</dt><dd class="mt-0.5 font-semibold text-fg" dir="ltr">{{ $invoice->paymentLink?->reference ?? '—' }}</dd></div>
        </dl>
        @if ($invoice->status === \App\Enums\InvoiceStatus::Void)
            <p class="mt-4 text-sm text-fg-secondary">{{ __('Voided on :date: :reason', ['date' => $invoice->voided_at?->translatedFormat('j M Y'), 'reason' => $invoice->void_reason]) }}</p>
        @endif
    </x-ui.card>

    @if ($invoice->status === \App\Enums\InvoiceStatus::Draft)
        @can('update', $invoice)
            <x-ui.card :title="__('Draft')">
                <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div class="md:col-span-2"><x-ui.input name="description" :label="__('Line description')" :value="$invoice->items->first()?->description" maxlength="255" required dir="auto" /></div>
                    <x-ui.input name="tax_rate" type="number" step="0.01" min="0" max="100" :label="__('Tax rate (%)')" :value="number_format($invoice->tax_rate_bp / 100, 2, '.', '')" dir="ltr" />
                    <x-ui.input name="due_at" type="date" :label="__('Due date')" :value="$invoice->due_at?->toDateString()" />
                    <div class="flex flex-col gap-1.5 md:col-span-2">
                        <label for="notes" class="text-sm font-semibold text-fg">{{ __('Notes (printed on the invoice)') }}</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="2000" dir="auto" class="w-full rounded-md border border-line bg-surface px-3 py-2.5 text-base text-fg">{{ old('notes', $invoice->notes) }}</textarea>
                    </div>
                    <div class="flex flex-wrap gap-3 md:col-span-2">
                        <x-ui.button variant="secondary">{{ __('Save draft') }}</x-ui.button>
                    </div>
                </form>
                <div class="mt-4 flex flex-wrap gap-3 border-t border-line pt-4">
                    <form method="POST" action="{{ route('admin.invoices.issue', $invoice) }}" data-confirm="{{ __('Issue this invoice? It gets the next invoice number and can no longer be edited.') }}" data-confirm-label="{{ __('Issue invoice') }}">
                        @csrf
                        <x-ui.button>{{ __('Issue invoice') }}</x-ui.button>
                    </form>
                </div>
            </x-ui.card>
        @endcan
    @endif

    @if (in_array($invoice->status, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::Issued], true))
        @can('update', $invoice)
            <x-ui.card :title="__('Void invoice')">
                <form method="POST" action="{{ route('admin.invoices.void', $invoice) }}" class="flex flex-wrap items-end gap-3" data-confirm="{{ __('Void this invoice? Its number stays used and it cannot be reopened.') }}" data-confirm-label="{{ __('Void') }}">
                    @csrf
                    <div class="w-full sm:w-96"><x-ui.input name="reason" :label="__('Reason')" maxlength="255" required dir="auto" /></div>
                    <x-ui.button variant="danger">{{ __('Void') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endcan
    @endif
</x-layouts.admin>
