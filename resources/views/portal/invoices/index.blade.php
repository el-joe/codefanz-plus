@php($invoiceIcon = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>')
<x-layouts.portal :title="__('Invoices')" :crumb="__('Invoices')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        @include('portal._tabs', ['label' => __('Filter invoices'), 'tabs' => [
            [__('All'), route('portal.invoices.index'), empty($filters['status'])],
            [__('Unpaid (:count)', ['count' => $unpaidCount]), route('portal.invoices.index', ['status' => 'unpaid']), ($filters['status'] ?? null) === 'unpaid'],
            [__('Paid'), route('portal.invoices.index', ['status' => 'paid']), ($filters['status'] ?? null) === 'paid'],
            [__('Overdue'), route('portal.invoices.index', ['status' => 'overdue']), ($filters['status'] ?? null) === 'overdue'],
        ]])
        <form method="GET" action="{{ route('portal.invoices.index') }}" role="search">
            @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <label for="invoice-search" class="sr-only">{{ __('Search invoices') }}</label>
            <input id="invoice-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search invoices…') }}" dir="auto" class="w-56 rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg">
        </form>
    </div>

    @if ($invoices->isEmpty())
        <x-ui.card padding="p-6">
            @include('portal._empty-module', ['title' => __('No invoices yet'), 'description' => __('Invoices and payment requests from CodeFanz will be listed here, with a secure way to pay.'), 'icon' => $invoiceIcon])
        </x-ui.card>
    @else
        <x-ui.card padding="p-0" class="overflow-hidden">
            <ul class="divide-y divide-line-subtle md:hidden" data-invoice-cards>
                @foreach ($invoices as $invoice)
                    @include('portal.invoices._row', ['invoice' => $invoice, 'card' => true])
                @endforeach
            </ul>
            <table class="hidden w-full text-sm md:table" data-invoices>
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Invoice') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Project / milestone') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Issued') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Due') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Amount') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        @include('portal.invoices._row', ['invoice' => $invoice, 'card' => false])
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif

    <p class="flex items-center gap-2 text-xs text-fg-tertiary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" class="text-fg-success"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        {{ __('Invoices are issued by CodeFanz per milestone. Payment links open a secure checkout; amounts can’t be edited.') }}
    </p>
</x-layouts.portal>
