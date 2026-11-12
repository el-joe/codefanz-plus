{{-- Ordered, bilingual child rows. Works without JS (clear a row to remove it; one blank row is always offered). --}}

<x-ui.card :title="$title">
    <div data-repeater class="flex flex-col gap-4">
        <p class="text-sm text-fg-secondary">{{ $description }}</p>
        @error($group)<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
        <ol data-repeater-list class="flex flex-col gap-3">
            @foreach ($rows as $index => $row)
                @include('admin.services._row', ['group' => $group, 'index' => $index, 'row' => $row, 'fields' => $fields])
            @endforeach
            @include('admin.services._row', ['group' => $group, 'index' => count($rows), 'row' => [], 'fields' => $fields])
        </ol>
        <template data-repeater-template>
            @include('admin.services._row', ['group' => $group, 'index' => '__INDEX__', 'row' => [], 'fields' => $fields])
        </template>
        <div>
            <x-ui.button type="button" variant="secondary" size="sm" data-repeater-add hidden>{{ $addLabel }}</x-ui.button>
        </div>
    </div>
</x-ui.card>
