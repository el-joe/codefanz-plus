<x-ui.card :title="__('Documents')">
    <x-slot:action><x-ui.button size="sm" variant="secondary" :href="route('portal.documents.index', ['project' => $project->ulid])">{{ __('Upload') }}</x-ui.button></x-slot:action>
    @if ($documentGroups->isEmpty())
        <p class="text-sm text-fg-secondary">{{ __('No documents shared yet.') }}</p>
    @else
        @include('portal.documents._list', ['groups' => $documentGroups, 'showProject' => false])
    @endif
</x-ui.card>
