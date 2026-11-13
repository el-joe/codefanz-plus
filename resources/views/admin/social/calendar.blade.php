{{-- B07 Content calendar: month / week grid in the staff member's time zone. Approved posts can be dragged to another
     day (or moved with the small form on each item — the keyboard alternative); the server re-checks the approval
     and the time. Drafts and posts in review sit at their planned time as "pending approval". --}}
@php
    use App\Enums\SocialPostStatus;

    $user = auth()->user();
    $query = array_filter(['view' => $view, 'status' => $filters['status'] ?? null, 'platform' => $filters['platform'] ?? null, 'campaign' => $filters['campaign'] ?? null]);
    $weekdays = collect(array_slice($days, 0, 7))->map(fn ($day) => $day->locale(app()->getLocale())->translatedFormat('D'));
    $pending = [SocialPostStatus::Draft, SocialPostStatus::InReview, SocialPostStatus::ChangesRequested];
    $tone = fn ($post) => in_array($post->status, $pending, true) ? 'warning' : $post->status->tone();
    $chipClasses = [
        'warning' => 'bg-warning-subtle text-fg-warning',
        'success' => 'bg-success-subtle text-fg-success',
        'brand' => 'bg-brand-subtle text-fg-brand',
        'accent' => 'bg-accent-subtle text-fg-accent',
        'danger' => 'bg-danger-subtle text-fg-danger',
        'info' => 'bg-info-subtle text-fg-info',
        'neutral' => 'bg-muted text-fg-secondary',
    ];
    $title = $view === 'week'
        ? __('Week of :date', ['date' => $days[0]->locale(app()->getLocale())->translatedFormat('j M Y')])
        : $anchor->locale(app()->getLocale())->translatedFormat('F Y');
@endphp

<x-layouts.admin :title="__('Content calendar · :period', ['period' => $title])" :crumb="__('Social')">
    @include('admin.social._tabs')

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <nav class="flex gap-1 rounded-lg bg-subtle p-1" aria-label="{{ __('Calendar view') }}">
                @foreach (['month' => __('Month'), 'week' => __('Week')] as $mode => $label)
                    <a href="{{ route('admin.social.calendar', [...$query, 'view' => $mode, 'date' => $anchor->format('Y-m-d')]) }}" @if ($view === $mode) aria-current="page" @endif
                       class="rounded-md px-3 py-1.5 text-sm font-semibold {{ $view === $mode ? 'bg-surface text-fg shadow-xs ring-1 ring-line' : 'text-fg-secondary hover:bg-surface' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="flex items-center gap-1">
                <a href="{{ route('admin.social.calendar', [...$query, 'date' => $previous->format('Y-m-d')]) }}" class="rounded-md border border-line px-3 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">
                    <span aria-hidden="true" class="inline-block rtl:rotate-180">‹</span> {{ __('Previous') }}
                </a>
                <a href="{{ route('admin.social.calendar', [...$query, 'date' => $today]) }}" class="rounded-md border border-line px-3 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">{{ __('Today') }}</a>
                <a href="{{ route('admin.social.calendar', [...$query, 'date' => $next->format('Y-m-d')]) }}" class="rounded-md border border-line px-3 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">
                    {{ __('Next') }} <span aria-hidden="true" class="inline-block rtl:rotate-180">›</span>
                </a>
            </div>
        </div>

        <form method="GET" data-auto-submit class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="hidden" name="date" value="{{ $anchor->format('Y-m-d') }}">
            <x-ui.select name="platform" :label="__('Platform')" :value="$filters['platform'] ?? ''" :placeholder="__('All platforms')"
                         :options="collect(\App\Enums\SocialPlatform::cases())->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()])->all()" />
            <x-ui.select name="status" :label="__('Status')" :value="$filters['status'] ?? ''" :placeholder="__('All statuses')"
                         :options="collect(SocialPostStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" />
            @if ($campaigns !== [])
                <x-ui.select name="campaign" :label="__('Campaign')" :value="$filters['campaign'] ?? ''" :placeholder="__('All campaigns')" :options="array_combine($campaigns, $campaigns)" />
            @endif
            <noscript><x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button></noscript>
        </form>
    </div>

    <ul class="flex flex-wrap gap-2 text-xs" aria-label="{{ __('Legend') }}">
        <li><x-ui.badge tone="success">{{ __('Published') }}</x-ui.badge></li>
        <li><x-ui.badge tone="brand">{{ __('Scheduled') }}</x-ui.badge></li>
        <li><x-ui.badge tone="accent">{{ __('Approved') }}</x-ui.badge></li>
        <li><x-ui.badge tone="warning">{{ __('Pending approval') }}</x-ui.badge></li>
        <li><x-ui.badge tone="danger">{{ __('Failed · retry') }}</x-ui.badge></li>
    </ul>

    <p class="text-xs text-fg-tertiary">{{ __('Times are shown in :zone.', ['zone' => $timezone]) }} {{ __('Drag an approved post to another day, or use “Move” on the item.') }}</p>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface" data-social-calendar
         data-error-message="{{ __('The post could not be moved.') }}">
        <div class="grid min-w-[56rem] grid-cols-7">
            @foreach ($weekdays as $weekday)
                <div class="border-b border-line bg-subtle px-3 py-2 text-xs font-semibold text-fg-tertiary">{{ $weekday }}</div>
            @endforeach

            @foreach ($days as $day)
                @php
                    $key = $day->format('Y-m-d');
                    $outside = $view === 'month' && $day->month !== $anchor->month;
                    $items = $byDay->get($key, collect());
                @endphp
                <div class="flex {{ $view === 'week' ? 'min-h-80' : 'min-h-28' }} flex-col gap-1 border-b border-e border-line-subtle p-2 {{ $key === $today ? 'bg-brand-subtle/50' : '' }}"
                     data-calendar-day="{{ $key }}" aria-label="{{ $day->locale(app()->getLocale())->translatedFormat('l j F Y') }}">
                    <span class="text-xs font-semibold {{ $outside ? 'text-fg-tertiary' : 'text-fg' }}">{{ $day->day }}</span>
                    <ul class="flex flex-col gap-1" data-calendar-list>
                        @foreach ($items as $post)
                            @php
                                $at = $post->calendarAt()->timezone($timezone);
                                $movable = $post->status->canBeScheduled() && $post->isApproved() && $user->can('review', $post);
                                $platforms = $post->variants->map(fn ($variant) => $variant->platform->shortLabel())->unique()->implode(', ');
                            @endphp
                            <li class="rounded-md px-2 py-1 text-xs {{ $chipClasses[$tone($post)] ?? $chipClasses['neutral'] }} {{ $movable ? 'cursor-grab' : '' }}"
                                data-social-chip @if ($movable) draggable="true" data-reschedule-url="{{ route('admin.social.posts.reschedule', $post) }}" data-time="{{ $at->format('H:i') }}" @endif>
                                <a href="{{ route('admin.social.posts.show', $post) }}" class="block truncate font-semibold hover:underline" dir="auto">
                                    <span dir="ltr">{{ $at->format('H:i') }}</span> · {{ $platforms ?: __('No accounts') }} · {{ $post->title }}
                                </a>
                                <span class="sr-only">{{ in_array($post->status, $pending, true) ? __('Pending approval') : $post->status->label() }}</span>
                                @if ($movable)
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-[11px] underline">{{ __('Move') }}</summary>
                                        <form method="POST" action="{{ route('admin.social.posts.reschedule', $post) }}" class="mt-1 flex flex-col gap-1">
                                            @csrf
                                            <label class="sr-only" for="move-{{ $post->ulid }}">{{ __('New date and time') }}</label>
                                            <input id="move-{{ $post->ulid }}" type="datetime-local" name="scheduled_for" required value="{{ $at->format('Y-m-d\TH:i') }}"
                                                   class="rounded border border-line-strong bg-surface px-1.5 py-1 text-xs text-fg">
                                            <button type="submit" class="rounded bg-brand px-2 py-1 text-xs font-semibold text-fg-on-brand">{{ __('Move') }}</button>
                                        </form>
                                    </details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <x-ui.alert tone="warning" :title="__('Scheduled ≠ approved')">
        {{ __('Items stay “Pending approval” until approved. The scheduler skips anything unapproved or paused by a kill switch.') }}
    </x-ui.alert>
</x-layouts.admin>
