<x-layouts.admin :title="__('Prompt library')" :crumb="__('AI Content Studio')">
    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="route('admin.brand-voice.edit')">{{ __('Brand voice') }}</x-ui.button>
        <x-ui.button :href="route('admin.prompts.create')">{{ __('New prompt') }}</x-ui.button>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($templates->isEmpty())
            <x-ui.empty-state :title="__('No prompt templates yet')" :description="__('Create a template for each kind of content you generate.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Name') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Purpose') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Language') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Version') }}</th>
                        <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Generations') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.prompts.edit', $template) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ __($template->name) }}</a>
                                <span class="block font-mono text-xs text-fg-tertiary" dir="ltr">{{ $template->key }}</span>
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $template->purpose->label() }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $template->locale ? strtoupper($template->locale) : __('Any') }}</td>
                            <td class="px-4 py-3 font-mono text-fg-secondary" dir="ltr">v{{ $template->version }}</td>
                            <td class="px-4 py-3 text-end text-fg-secondary">{{ number_format($template->generations_count) }}</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$template->is_active ? 'success' : 'neutral'">{{ $template->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</x-layouts.admin>
