{{-- Payment links table. Expects $links; $showClient (default true). --}}
@php($showClient = $showClient ?? true)
<table class="w-full text-sm" data-links-table>
    <thead class="bg-subtle text-xs text-fg-tertiary">
        <tr>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Reference') }}</th>
            @if ($showClient)<th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Client · Project') }}</th>@endif
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Description') }}</th>
            <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Amount') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Expires') }}</th>
            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
            <th scope="col" class="px-4 py-3 text-end font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($links as $link)
            @php($status = $link->effectiveStatus())
            <tr class="border-t border-line-subtle align-top" data-link="{{ $link->ulid }}">
                <td class="px-4 py-3">
                    <p class="font-mono text-xs font-semibold text-fg" dir="ltr">{{ $link->reference }}</p>
                    <p class="text-xs text-fg-tertiary">{{ $link->single_use ? __('Single use') : __('Reusable') }}</p>
                </td>
                @if ($showClient)
                    <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $link->customer?->company_name }} · {{ $link->project?->name }}</td>
                @endif
                <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $link->description ?: '—' }}@if ($link->milestone)<span class="block text-xs text-fg-tertiary">{{ $link->milestone->title }}</span>@endif</td>
                <td class="px-4 py-3 text-end font-semibold text-fg" dir="ltr">{{ \App\Services\Money::plain($link->amount_minor, $link->currency) }}</td>
                <td class="px-4 py-3 text-fg-secondary">{{ $link->expires_at?->translatedFormat('j M Y') ?? __('No expiry') }}</td>
                <td class="px-4 py-3"><x-ui.badge :tone="$status->tone()">{{ $status->label() }}</x-ui.badge></td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap justify-end gap-1.5">
                        @if ($status === \App\Enums\PaymentLinkStatus::Active)
                            <x-ui.button size="sm" variant="secondary" type="button" data-copy="{{ $link->publicUrl() }}" data-copied-label="{{ __('Copied') }}">{{ __('Copy link') }}</x-ui.button>
                        @endif
                        @can('create', \App\Models\Invoice::class)
                            <form method="POST" action="{{ route('admin.payment-links.invoice', $link) }}">
                                @csrf
                                <x-ui.button size="sm" variant="ghost">{{ __('Invoice') }}</x-ui.button>
                            </form>
                        @endcan
                        @if ($link->status === \App\Enums\PaymentLinkStatus::Active)
                            @can('cancel', $link)
                                <form method="POST" action="{{ route('admin.payment-links.cancel', $link) }}" data-confirm="{{ __('Cancel payment link :reference? The client will no longer be able to pay it.', ['reference' => $link->reference]) }}" data-confirm-label="{{ __('Cancel link') }}">
                                    @csrf
                                    <button type="submit" class="rounded-md px-2 py-1 text-sm text-fg-danger hover:bg-subtle">{{ __('Cancel') }}</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
