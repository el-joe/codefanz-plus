<x-layouts.admin :title="__('Generation history')" :crumb="__('AI Content Studio')">
    <p class="text-sm text-fg-secondary">{{ $seesAll ? __('Every generation by every author, newest first.') : __('Your generations, newest first.') }}</p>

    <x-ui.card padding="p-0" class="overflow-hidden">
        @forelse ($generations as $generation)
            @include('admin.ai-studio._generation-row', ['generation' => $generation, 'showUser' => true])
        @empty
            <x-ui.empty-state :title="__('Nothing generated yet')" :description="__('Pick a template in the studio to write your first draft.')" />
        @endforelse
    </x-ui.card>

    {{ $generations->links() }}
</x-layouts.admin>
