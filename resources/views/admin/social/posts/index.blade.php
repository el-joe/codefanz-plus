{{-- B06 Social media publisher: post list with workflow status per post and delivery status per platform. --}}
<x-layouts.admin :title="__('Social media publisher')" :crumb="__('AI & Automation')">
    @include('admin.social._tabs')

    @if ($needsExport > 0)
        <x-ui.alert tone="warning" :title="__('Waiting for manual export')" data-needs-export>
            {{ __(':count approved variant(s) could not be published through an API and are ready to be posted by hand.', ['count' => $needsExport]) }}
            <a href="{{ route('admin.social.posts.index', ['status' => 'publishing']) }}" class="font-semibold text-fg-brand hover:underline">{{ __('Show them') }}</a>
        </x-ui.alert>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" data-auto-submit class="flex flex-wrap items-end gap-3">
            <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? ''" :placeholder="__('All statuses')"
                         :options="collect(\App\Enums\SocialPostStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
            <x-ui.select name="platform" :label="__('Platform')" :value="$filters['platform'] ?? ''" :placeholder="__('All platforms')"
                         :options="collect(\App\Enums\SocialPlatform::cases())->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()])->all()" />
            @if ($campaigns !== [])
                <x-ui.select name="campaign" :label="__('Campaign')" :value="$filters['campaign'] ?? ''" :placeholder="__('All campaigns')"
                             :options="array_combine($campaigns, $campaigns)" />
            @endif
            <noscript><x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button></noscript>
        </form>
        <x-ui.button :href="route('admin.social.posts.create')">{{ __('New post') }}</x-ui.button>
    </div>

    <x-ui.card padding="p-0" class="overflow-x-auto">
        @if ($posts->isEmpty())
            <x-ui.empty-state :title="__('No social posts yet')" :description="__('Write one post, adapt it per platform, and send it for approval. Nothing is published without an approval.')" />
        @else
            <table class="w-full text-sm">
                <thead class="bg-subtle text-xs text-fg-tertiary">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Post') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Platforms') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('When') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($posts as $post)
                        @php($when = $post->calendarAt())
                        <tr class="border-t border-line-subtle">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.social.posts.show', $post) }}" class="font-semibold text-fg hover:text-fg-brand" dir="auto">{{ $post->title }}</a>
                                <p class="text-xs text-fg-tertiary">
                                    {{ strtoupper($post->locale) }}@if ($post->campaign) · <span dir="auto">{{ $post->campaign }}</span>@endif
                                    @if ($post->isAiOrigin()) · {{ __('AI draft') }}@endif
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="flex flex-wrap gap-1.5">
                                    @forelse ($post->variants as $variant)
                                        <x-ui.badge :tone="$variant->status->tone()" :title="$variant->status->label()">{{ $variant->platform->shortLabel() }}<span class="sr-only">: {{ $variant->status->label() }}</span></x-ui.badge>
                                    @empty
                                        <span class="text-xs text-fg-tertiary">{{ __('No accounts chosen') }}</span>
                                    @endforelse
                                </span>
                            </td>
                            <td class="px-4 py-3"><x-ui.badge :tone="$post->status->tone()">{{ $post->status->label() }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-fg-secondary">
                                @if ($when)
                                    <time datetime="{{ $when->toIso8601String() }}">{{ $when->timezone(auth()->user()->timezone ?: config('app.timezone'))->translatedFormat('j M Y · H:i') }}</time>
                                    @if (! $post->scheduled_at && ! $post->published_at)<span class="text-xs text-fg-tertiary">· {{ __('planned') }}</span>@endif
                                @else
                                    —
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
