<x-layouts.admin :title="__('AI Content Studio')" :crumb="__('AI & Automation')">
    <div class="flex flex-wrap items-center justify-end gap-2">
        <x-ui.button variant="secondary" size="sm" :href="route('admin.ai-studio.generations.index')">{{ __('Generation history') }}</x-ui.button>
        @can('ai.manage')
            <x-ui.button variant="secondary" size="sm" :href="route('admin.prompts.index')">{{ __('Prompt library') }}</x-ui.button>
            <x-ui.button variant="secondary" size="sm" :href="route('admin.brand-voice.edit')">{{ __('Brand voice') }}</x-ui.button>
        @endcan
    </div>

    @include('admin.ai-studio._banner')

    <section aria-labelledby="create-heading" class="flex flex-col gap-4">
        <h2 id="create-heading" class="text-xl font-semibold text-fg">{{ __('What would you like to create?') }}</h2>
        @if ($templates->isEmpty())
            <x-ui.card><x-ui.empty-state :title="__('No prompt templates yet')" :description="__('Add templates in the prompt library first.')" /></x-ui.card>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-template-gallery>
                @foreach ($templates as $template)
                    <a href="{{ route('admin.ai-studio.create', ['template' => $template->key]) }}" class="flex flex-col gap-3 rounded-xl border border-line bg-surface p-5 hover:border-line-strong hover:bg-subtle">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-brand-subtle text-fg-brand"><x-ui.icon :name="$template->purpose->icon()" size="18" /></span>
                        <span class="font-semibold text-fg" dir="auto">{{ __($template->name) }}</span>
                        <span class="text-xs text-fg-tertiary" dir="auto">{{ $template->description ? __($template->description) : $template->purpose->description() }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <x-ui.card padding="p-0" class="overflow-hidden">
        <div class="flex items-center gap-3 border-b border-line px-5 py-4">
            <h2 class="flex-1 text-lg font-semibold text-fg">{{ __('Recent generations') }}</h2>
            <a href="{{ route('admin.ai-studio.generations.index') }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('View all') }}</a>
        </div>
        @forelse ($recent as $generation)
            @include('admin.ai-studio._generation-row', ['generation' => $generation])
        @empty
            <x-ui.empty-state :title="__('Nothing generated yet')" :description="__('Pick a template above to write your first draft.')" />
        @endforelse
    </x-ui.card>
</x-layouts.admin>
