{{-- Status change form: the keyboard / no-JavaScript alternative to dragging on the board, and the detail-page control. --}}
@php
    $compact ??= false;
    $allowed = $lead->status->allowedTransitions();
@endphp

@if ($allowed)
    <form method="POST" action="{{ route('admin.leads.status', $lead) }}" data-lead-move class="{{ $compact ? 'flex flex-wrap items-end gap-2' : 'flex flex-col gap-3' }}">
        @csrf
        <input type="hidden" name="lock_version" value="{{ $lead->lock_version }}" data-lock-version>
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <label for="move-{{ $lead->ulid }}-{{ $compact ? 'card' : 'detail' }}" class="{{ $compact ? 'sr-only' : 'text-sm font-semibold text-fg' }}">{{ __('Move :reference to', ['reference' => $lead->reference]) }}</label>
            <select id="move-{{ $lead->ulid }}-{{ $compact ? 'card' : 'detail' }}" name="status" data-lead-move-status
                    class="w-full rounded-md border border-line-strong bg-surface px-2 py-1.5 text-sm text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20">
                @foreach ($allowed as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex min-w-0 flex-1 flex-col gap-1" data-lost-reason-field>
            <label for="lost-{{ $lead->ulid }}-{{ $compact ? 'card' : 'detail' }}" class="{{ $compact ? 'sr-only' : 'text-sm font-semibold text-fg' }}">{{ __('Reason if lost') }}</label>
            <input type="text" id="lost-{{ $lead->ulid }}-{{ $compact ? 'card' : 'detail' }}" name="lost_reason" maxlength="500" placeholder="{{ __('Reason if lost') }}" data-lead-move-reason
                   class="w-full rounded-md border border-line-strong bg-surface px-2 py-1.5 text-sm text-fg placeholder:text-fg-tertiary focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20">
        </div>
        <x-ui.button size="sm" variant="secondary">{{ __('Move') }}</x-ui.button>
    </form>
@endif
