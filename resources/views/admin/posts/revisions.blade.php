{{-- Revision history: list, side-by-side text diff of a revision against the current article, and restore. --}}
@php
    $timezone = auth()->user()->timezone ?: config('app.timezone');
    $rowTone = fn (string $type, string $side) => match (true) {
        $type === 'same' => 'text-fg-secondary',
        $side === 'left' && in_array($type, ['removed', 'changed'], true) => 'bg-danger-subtle text-fg',
        $side === 'right' && in_array($type, ['added', 'changed'], true) => 'bg-success-subtle text-fg',
        default => 'bg-subtle',
    };
@endphp

<x-layouts.admin :title="__('Revision history')" :crumb="__('Blog')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-fg-secondary" dir="auto">{{ $post->translation()?->title ?? __('Untitled') }} · {{ __('Version :version', ['version' => $post->lock_version]) }}</p>
        <x-ui.button :href="route('admin.posts.edit', $post)" variant="secondary">{{ __('Back to the editor') }}</x-ui.button>
    </div>

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Could not restore')">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <x-ui.card :title="__('Revisions')" padding="p-0" class="[&>div]:px-6 [&>div]:pt-5">
            @if ($history->isEmpty())
                <x-ui.empty-state :title="__('No revisions yet')" :description="__('A revision is saved every time the content changes.')" />
            @else
                <ol class="flex flex-col">
                    @foreach ($history as $revision)
                        @php($isSelected = $selected?->is($revision))
                        <li class="flex flex-col gap-1 border-t border-line-subtle px-6 py-3 {{ $isSelected ? 'bg-brand-subtle' : '' }}">
                            <a href="{{ route('admin.posts.revisions.index', ['post' => $post, 'revision' => $revision->id, 'locale' => $locale]) }}" class="text-sm font-semibold text-fg hover:text-fg-brand" @if ($isSelected) aria-current="true" @endif>
                                {{ $revision->created_at->timezone($timezone)->translatedFormat('j M Y, H:i') }}
                                · {{ __('v:version', ['version' => $revision->lock_version]) }}
                            </a>
                            <span class="text-xs text-fg-tertiary">{{ $revision->reasonLabel() }} · {{ $revision->actor_label ?? $revision->user?->name ?? __('System') }}</span>
                        </li>
                    @endforeach
                </ol>
                <div class="px-6 py-3">{{ $history->links() }}</div>
            @endif
        </x-ui.card>

        <x-ui.card :title="$selected ? __('Changes since this revision') : __('Compare')">
            @if (! $selected)
                <p class="text-sm text-fg-secondary">{{ __('Choose a revision to compare it with the current article and restore it if needed.') }}</p>
            @else
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <nav aria-label="{{ __('Language') }}">
                            <ul class="inline-flex gap-1 rounded-lg bg-muted p-1 text-sm font-semibold">
                                @foreach (\App\Models\Language::active() as $code => $language)
                                    <li><a href="{{ route('admin.posts.revisions.index', ['post' => $post, 'revision' => $selected->id, 'locale' => $code]) }}" lang="{{ $code }}" @if ($code === $locale) aria-current="page" @endif
                                           class="inline-block rounded-md px-3 py-1.5 {{ $code === $locale ? 'bg-surface text-fg shadow-sm' : 'text-fg-secondary hover:text-fg' }}">{{ $language['native_name'] }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                        <form method="POST" action="{{ route('admin.posts.revisions.restore', [$post, $selected]) }}" data-confirm="{{ __('Restore this revision? It is saved as a new version; nothing is lost.') }}" data-confirm-label="{{ __('Restore') }}">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $post->lock_version }}">
                            <x-ui.button>{{ __('Restore this revision') }}</x-ui.button>
                        </form>
                    </div>

                    @if ($rows === [])
                        <p class="text-sm text-fg-secondary">{{ __('No content in this language in either version.') }}</p>
                    @elseif (collect($rows)->every(fn ($row) => $row['type'] === 'same'))
                        <p class="text-sm text-fg-secondary">{{ __('This revision matches the current article in this language.') }}</p>
                    @endif

                    @if ($rows !== [])
                        <div class="overflow-x-auto">
                            <table class="w-full table-fixed text-sm" data-diff dir="{{ \App\Models\Language::active()[$locale]['direction'] ?? 'ltr' }}" lang="{{ $locale }}">
                                <caption class="sr-only">{{ __('Side-by-side comparison') }}</caption>
                                <thead class="bg-subtle text-xs text-fg-tertiary">
                                    <tr>
                                        <th scope="col" class="w-1/2 px-3 py-2 text-start font-semibold">{{ __('Selected revision') }}</th>
                                        <th scope="col" class="w-1/2 px-3 py-2 text-start font-semibold">{{ __('Current article') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="align-top">
                                    @foreach ($rows as $row)
                                        <tr class="border-t border-line-subtle" data-diff-row="{{ $row['type'] }}">
                                            <td class="whitespace-pre-wrap break-words px-3 py-1.5 {{ $rowTone($row['type'], 'left') }}">@if ($row['left'] !== null && $row['type'] !== 'same')<span class="sr-only">{{ __('Removed:') }} </span>@endif{{ $row['left'] }}</td>
                                            <td class="whitespace-pre-wrap break-words px-3 py-1.5 {{ $rowTone($row['type'], 'right') }}">@if ($row['right'] !== null && $row['type'] !== 'same')<span class="sr-only">{{ __('Added:') }} </span>@endif{{ $row['right'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
