<x-layouts.admin :title="__('FAQ')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center gap-3">
        <p class="flex-1 text-sm text-fg-secondary">{{ __('Questions are grouped by category on the public FAQ page. Featured questions appear first in the homepage FAQ section.') }}</p>
        @can('create', \App\Models\FaqCategory::class)
            <x-ui.button variant="secondary" :href="route('admin.faq-categories.create')">{{ __('New category') }}</x-ui.button>
        @endcan
        @can('create', \App\Models\Faq::class)
            <x-ui.button :href="route('admin.faqs.create')">{{ __('New question') }}</x-ui.button>
        @endcan
    </div>

    <x-ui.card :title="__('Categories')">
        @if ($categories->isEmpty())
            <p class="text-sm text-fg-secondary">{{ __('No categories yet. Categories become the groups on the public FAQ page.') }}</p>
        @else
            <ul class="flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('admin.faq-categories.edit', $category) }}" class="inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-sm font-semibold text-fg hover:border-line-brand" dir="auto">
                            {{ $category->translation()?->name ?? '#'.$category->id }}
                            <span class="text-xs font-normal text-fg-tertiary">{{ $category->faqs_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($faqs->isEmpty())
            <x-ui.empty-state :title="__('No questions yet')" :description="__('Answer the questions clients ask most, in both languages.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Question') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Category') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($faqs as $faq)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.faqs.edit', $faq) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $faq->translation()?->question ?? __('Untitled') }}</a>
                                @if ($faq->is_featured)
                                    <x-ui.badge tone="brand" :dot="false">{{ __('Featured') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary" dir="auto">{{ $faq->category?->translation()?->name ?? '—' }}</td>
                            <td class="px-4 py-3">@include('admin._languages', ['model' => $faq])</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$faq->status === \App\Enums\ContentStatus::Published ? 'success' : 'neutral'">{{ $faq->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $faq->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $faqs->links() }}
</x-layouts.admin>
