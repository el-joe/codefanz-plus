<x-ui.card>
    <form method="POST" action="{{ $action }}" data-confirm="{{ $confirm }}" data-confirm-label="{{ $label }}">
        @csrf
        @method('DELETE')
        <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ $label }}</x-ui.button>
    </form>
</x-ui.card>
