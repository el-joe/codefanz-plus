<x-layouts.admin :title="__('Homepage sections')" :crumb="__('Pages')">
    <p class="text-sm text-fg-secondary">{{ __('Reorder, show or hide the homepage sections and edit their copy in each language. Lists (services, projects, statistics…) come from their own admin screens; a section with nothing published stays hidden automatically.') }}</p>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-subtle text-xs text-fg-tertiary">
                <tr>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">#</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Section') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Content') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Copy') }}</th>
                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                    <th scope="col" class="px-4 py-3 text-end font-semibold">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sections as $section)
                    <tr class="border-t border-line-subtle" data-section-row="{{ $section->type->value }}">
                        <td class="px-4 py-3 text-fg-tertiary">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3"><a href="{{ route('admin.home-sections.edit', $section) }}" class="font-semibold text-fg hover:text-fg-brand">{{ $section->type->label() }}</a></td>
                        <td class="px-4 py-3 text-fg-secondary">{{ $section->type->source() }}</td>
                        <td class="px-4 py-3">
                            <span class="flex gap-1.5">
                                @foreach (\App\Models\Language::active() as $code => $language)
                                    <x-ui.badge :tone="$section->translations->contains('locale', $code) ? 'success' : 'neutral'" :dot="false" title="{{ $section->translations->contains('locale', $code) ? __('Custom copy') : __('Default copy') }}">{{ strtoupper($code) }}</x-ui.badge>
                                @endforeach
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if (! $section->type->isAvailable())
                                <x-ui.badge tone="warning">{{ __('Coming soon') }}</x-ui.badge>
                            @else
                                <x-ui.badge :tone="$section->is_visible ? 'success' : 'neutral'">{{ $section->is_visible ? __('Visible') : __('Hidden') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-1">
                                @foreach (['up' => __('Move up'), 'down' => __('Move down')] as $direction => $label)
                                    <form method="POST" action="{{ route('admin.home-sections.move', $section) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button type="submit" @disabled(($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last))
                                                class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-secondary hover:bg-subtle disabled:opacity-40">{{ $label }}<span class="sr-only">: {{ $section->type->label() }}</span></button>
                                    </form>
                                @endforeach
                                @if ($section->type->isAvailable())
                                    <form method="POST" action="{{ route('admin.home-sections.visibility', $section) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md px-2.5 py-1 text-xs font-semibold text-fg-brand hover:bg-brand-subtle">{{ $section->is_visible ? __('Hide') : __('Show') }}<span class="sr-only">: {{ $section->type->label() }}</span></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.admin>
