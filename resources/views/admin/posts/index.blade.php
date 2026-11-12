@php
    $timezone = auth()->user()->timezone ?: config('app.timezone');
    $statusOptions = collect(\App\Enums\PostStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label().' ('.($counts[$status->value] ?? 0).')'])->all();
@endphp

<x-layouts.admin :title="__('Blog')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="{{ __('Blog sections') }}">
            <ul class="inline-flex gap-1 rounded-lg bg-muted p-1 text-sm font-semibold">
                <li><a href="{{ route('admin.posts.index') }}" aria-current="page" class="inline-block rounded-md bg-surface px-3 py-1.5 text-fg shadow-sm">{{ __('Articles') }}</a></li>
                @can('viewAny', \App\Models\PostCategory::class)
                    <li><a href="{{ route('admin.post-categories.index') }}" class="inline-block rounded-md px-3 py-1.5 text-fg-secondary hover:text-fg">{{ __('Categories & tags') }}</a></li>
                @endcan
            </ul>
        </nav>
        @can('create', \App\Models\Post::class)
            <x-ui.button :href="route('admin.posts.create')">{{ __('New article') }}</x-ui.button>
        @endcan
    </div>

    <form method="GET" action="{{ route('admin.posts.index') }}" role="search" class="flex flex-wrap items-end gap-3" data-auto-submit>
        <div class="min-w-48 flex-1">
            <x-ui.input name="q" type="search" :label="__('Search titles')" :value="$filters['q']" maxlength="100" />
        </div>
        <x-ui.select name="status" :label="__('Status')" :value="$filters['status']" :placeholder="__('All statuses')" :options="$statusOptions" />
        <x-ui.select name="author" :label="__('Author')" :value="$filters['author']" :placeholder="__('All authors')" :options="$authors->pluck('name', 'id')->all()" />
        <x-ui.select name="category" :label="__('Category')" :value="$filters['category']" :placeholder="__('All categories')"
                     :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->translation()?->name ?? '#'.$category->id])->all()" />
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($posts->isEmpty())
            <x-ui.empty-state :title="__('No articles yet')" :description="__('Write articles in English and Arabic, send them for review and publish or schedule them.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Title') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Author') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Category') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Languages') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($posts as $post)
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.posts.edit', $post) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $post->translation()?->title ?? __('Untitled') }}</a>
                                @if ($post->isAiOrigin())
                                    <x-ui.badge tone="warning" class="ms-2">{{ __('AI draft') }}</x-ui.badge>
                                @endif
                                @if ($post->is_featured)
                                    <x-ui.badge tone="accent" :dot="false" class="ms-1">{{ __('Featured') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $post->author?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-fg-secondary">{{ $post->category?->translation()?->name ?? '—' }}</td>
                            <td class="px-4 py-3">@include('admin._languages', ['model' => $post])</td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$post->status->tone()">{{ $post->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">
                                @if ($post->status === \App\Enums\PostStatus::Scheduled && $post->scheduled_for)
                                    {{ __('Goes live :date', ['date' => $post->scheduled_for->timezone($timezone)->translatedFormat('j M Y, H:i')]) }}
                                @elseif ($post->published_at)
                                    {{ $post->published_at->timezone($timezone)->translatedFormat('j M Y') }}
                                @else
                                    {{ __('Updated :date', ['date' => $post->updated_at->diffForHumans()]) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    {{ $posts->links() }}
</x-layouts.admin>
