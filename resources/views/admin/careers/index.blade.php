<x-layouts.admin :title="__('Careers')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('Published positions appear on the careers page until their closing date. Applications are sent by email for now.') }}</p>
        @can('create', \App\Models\Career::class)
            <x-ui.button :href="route('admin.careers.create')">{{ __('New position') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($careers->isEmpty())
            <x-ui.empty-state :title="__('No positions yet')" :description="__('Add open roles in both languages.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Title') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Employment type') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Closes') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($careers as $career)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.careers.edit', $career) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $career->translation()?->title ?? __('Untitled') }}</a>
                                @if ($career->is_remote)
                                    <x-ui.badge tone="accent" :dot="false">{{ __('Remote') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $career->employment_type->label() }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $career->closes_at?->translatedFormat('j M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">@include('admin._languages', ['model' => $career])</td>
                            <td class="px-4 py-3">
                                <x-ui.badge :tone="$career->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $career->status->label() }}</x-ui.badge>
                                @if ($career->status === \App\Enums\ContentStatus::Published && ! $career->isOpen())
                                    <x-ui.badge tone="warning" :dot="false">{{ __('Closed') }}</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $careers->links() }}
</x-layouts.admin>
