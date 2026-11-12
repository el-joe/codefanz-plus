<x-layouts.admin :title="__('Pages')" :crumb="__('Content & SEO')">
    <div class="flex justify-end">
        @can('create', \App\Models\Page::class)
            <x-ui.button :href="route('admin.pages.create')">{{ __('New page') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($pages->isEmpty())
            <x-ui.empty-state :title="__('No pages yet')" :description="__('Create pages such as Privacy Policy or Terms in both languages.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Title') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Updated') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        @php($translation = $page->translation() ?? $page->translations->first())
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3"><a href="{{ route('admin.pages.edit', $page) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $translation?->title ?? __('Untitled') }}</a></td>
                            <td class="px-4 py-3">
                                <span class="flex gap-1.5">
                                    @foreach (\App\Models\Language::active() as $code => $language)
                                        <x-ui.badge :tone="$page->translations->contains('locale', $code) ? 'success' : 'neutral'" :dot="false">{{ strtoupper($code) }}</x-ui.badge>
                                    @endforeach
                                </span>
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$page->status === \App\Enums\PageStatus::Published ? 'success' : 'neutral'">{{ __(ucfirst($page->status->value)) }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $page->updated_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $pages->links() }}
</x-layouts.admin>
