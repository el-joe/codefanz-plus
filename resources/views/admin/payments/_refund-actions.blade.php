{{-- Four-eyes actions for one refund. Expects $refund. The requester never sees approve/reject (RefundPolicy). --}}
@can('approve', $refund)
    <div class="flex flex-wrap items-end gap-2" data-refund-decision="{{ $refund->ulid }}">
        <form method="POST" action="{{ route('admin.refunds.approve', $refund) }}" data-confirm="{{ __('Approve refund :reference of :amount? It is sent to the gateway immediately.', ['reference' => $refund->reference, 'amount' => \App\Services\Money::plain($refund->amount_minor, $refund->currency)]) }}" data-confirm-label="{{ __('Approve refund') }}">
            @csrf
            <x-ui.button size="sm">{{ __('Approve') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ route('admin.refunds.reject', $refund) }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <label class="sr-only" for="reject-note-{{ $refund->ulid }}">{{ __('Reason for rejecting') }}</label>
            <input id="reject-note-{{ $refund->ulid }}" name="note" required maxlength="500" dir="auto" placeholder="{{ __('Reason for rejecting') }}" class="w-48 rounded-md border border-line-strong bg-surface px-2.5 py-1.5 text-sm text-fg">
            <x-ui.button size="sm" variant="secondary">{{ __('Reject') }}</x-ui.button>
        </form>
    </div>
@elseif ($refund->status === \App\Enums\RefundStatus::Requested && $refund->requested_by === auth()->id())
    <p class="text-xs text-fg-tertiary">{{ __('Waiting for another finance approver (you requested it).') }}</p>
@endif

@can('complete', $refund)
    <form method="POST" action="{{ route('admin.refunds.complete', $refund) }}" class="flex flex-wrap items-end gap-2" data-refund-manual="{{ $refund->ulid }}"
          data-confirm="{{ __('Confirm that :amount was returned to the client outside the gateway?', ['amount' => \App\Services\Money::plain($refund->amount_minor, $refund->currency)]) }}" data-confirm-label="{{ __('Record manual refund') }}">
        @csrf
        <label class="sr-only" for="external-reference-{{ $refund->ulid }}">{{ __('External reference') }}</label>
        <input id="external-reference-{{ $refund->ulid }}" name="external_reference" required maxlength="191" dir="ltr" placeholder="{{ __('Bank transfer or receipt reference') }}" class="w-56 rounded-md border border-line-strong bg-surface px-2.5 py-1.5 text-sm text-fg">
        <x-ui.button size="sm">{{ __('Record manual refund') }}</x-ui.button>
    </form>
@endcan
