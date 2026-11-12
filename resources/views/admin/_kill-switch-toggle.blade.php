{{-- One emergency control with its pause / resume form (audited through admin.emergency.update). --}}
<div class="flex flex-col gap-3 rounded-lg border border-line p-4" data-kill-switch="{{ $key->value }}">
    <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-semibold text-fg">{{ $key->label() }}</h3>
        <x-ui.badge :tone="$active ? 'danger' : 'success'">{{ $active ? __('Paused') : __('Running') }}</x-ui.badge>
    </div>
    <p class="text-xs text-fg-tertiary">{{ $key->description() }}</p>
    <form method="POST" action="{{ route('admin.emergency.update', $key->value) }}" class="flex flex-col gap-2"
          @unless ($active) data-confirm="{{ __('Pause this immediately for everyone?') }}" data-confirm-label="{{ __('Pause') }}" @endunless>
        @csrf
        @method('PUT')
        <input type="hidden" name="active" value="{{ $active ? 0 : 1 }}">
        <label class="sr-only" for="reason-{{ $key->value }}">{{ __('Reason') }}</label>
        <input id="reason-{{ $key->value }}" name="reason" maxlength="255" placeholder="{{ __('Reason (optional)') }}" class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg">
        <x-ui.button :variant="$active ? 'secondary' : 'danger'" size="sm">{{ $active ? __('Resume') : __('Pause now') }}</x-ui.button>
    </form>
</div>
