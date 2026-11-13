{{-- B06 post page: workflow (submit → approve → schedule / publish now), per-platform delivery status, retries,
     the manual export panel for accounts without API publishing, metrics state and the audit trail. --}}
@php
    use App\Enums\SocialPostStatus;
    use App\Enums\SocialVariantStatus;

    $user = auth()->user();
    $timezone = $user->timezone ?: config('app.timezone');
    $status = $post->status;
    $canEdit = $user->can('update', $post);
    $scheduleDefault = old('scheduled_for', ($post->scheduled_at ?? $post->planned_at ?? now()->addDay()->setTime(9, 0))->timezone($timezone)->format('Y-m-d\TH:i'));
    $generatedByMe = $post->isGeneratedBy($user);
    $cancellable = in_array($status, [SocialPostStatus::Draft, SocialPostStatus::InReview, SocialPostStatus::ChangesRequested, SocialPostStatus::Approved, SocialPostStatus::Scheduled, SocialPostStatus::Failed], true);
    $copied = __('Copied');
@endphp

<x-layouts.admin :title="$post->title" :crumb="__('Social')">
    @include('admin.social._tabs')

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('This action could not be completed')">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($paused && in_array($status, [SocialPostStatus::Scheduled, SocialPostStatus::Publishing], true))
        <x-ui.alert tone="warning" :title="__('Publishing is paused')" data-social-paused>{{ __('A kill switch is active. Nothing is sent to the platforms until it is turned off.') }}</x-ui.alert>
    @endif

    @if ($post->isAiOrigin() && ! in_array($status, [SocialPostStatus::Published, SocialPostStatus::ExportedManually], true))
        <x-ui.alert tone="warning" :title="__('AI draft')">{{ __('This copy was written with AI. Check every fact, number and link. Someone other than the person who generated it must approve it.') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge :tone="$status->tone()" data-post-status="{{ $status->value }}">{{ $status->label() }}</x-ui.badge>
                    @if ($post->isAiOrigin())<x-ui.badge tone="info">{{ __('AI draft') }}</x-ui.badge>@endif
                    <x-ui.badge :dot="false">{{ strtoupper($post->locale) }}</x-ui.badge>
                    @if ($post->campaign)<x-ui.badge tone="accent" :dot="false"><span dir="auto">{{ $post->campaign }}</span></x-ui.badge>@endif
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-fg-tertiary">{{ __('Author') }}</dt><dd class="text-fg">{{ $post->author?->name ?? '—' }}</dd></div>
                    <div><dt class="text-fg-tertiary">{{ __('Approved by') }}</dt><dd class="text-fg">{{ $post->isApproved() ? $post->reviewer?->name.' · '.$post->approved_at->timezone($timezone)->translatedFormat('j M Y H:i') : __('Not approved yet') }}</dd></div>
                    <div><dt class="text-fg-tertiary">{{ __('Scheduled for') }}</dt><dd class="text-fg">{{ $post->scheduled_at ? $post->scheduled_at->timezone($timezone)->translatedFormat('j M Y · H:i').' ('.$timezone.')' : ($post->planned_at ? __('Planned: :date', ['date' => $post->planned_at->timezone($timezone)->translatedFormat('j M Y · H:i')]) : '—') }}</dd></div>
                    <div><dt class="text-fg-tertiary">{{ __('Link') }}</dt><dd class="truncate text-fg" dir="ltr">{{ $post->link_url ?? '—' }}</dd></div>
                </dl>
                @if ($post->review_note)
                    <x-ui.alert tone="warning" :title="__('Reviewer note')" class="mt-4"><span dir="auto">{{ $post->review_note }}</span></x-ui.alert>
                @endif
            </x-ui.card>

            @if ($media->isNotEmpty())
                <x-ui.card :title="__('Images')">
                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($media as $image)
                            @php
                                $optimized = ($image->largestVariant('webp') ?? $image->largestVariant('gif'))?->url();
                                $alt = $post->altFor($image->id) ?: $image->altText($post->locale);
                            @endphp
                            <li class="flex flex-col gap-2 rounded-lg border border-line p-3 text-xs">
                                @if ($optimized)<img src="{{ $optimized }}" alt="" class="aspect-video w-full rounded object-cover" loading="lazy">@endif
                                <p class="truncate font-semibold text-fg">{{ $image->original_name }} · {{ $image->width }}×{{ $image->height }}</p>
                                <p class="text-fg-secondary" dir="auto">{{ __('Alt text') }}: {{ $alt ?: '—' }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.social.posts.media', [$post, $image]) }}" class="font-semibold text-fg-brand hover:underline">{{ __('Download original') }}</a>
                                    @if ($optimized)<a href="{{ $optimized }}" download class="font-semibold text-fg-brand hover:underline">{{ __('Download optimized') }}</a>@endif
                                    @if ($alt)<button type="button" data-copy="{{ $alt }}" data-copied-label="{{ $copied }}" class="font-semibold text-fg-brand hover:underline">{{ __('Copy alt text') }}</button>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            @forelse ($post->variants as $variant)
                @php
                    $account = $variant->account;
                    $composed = $variant->composedText($post);
                    $length = \App\Models\SocialPostVariant::measure($variant->platform, $composed);
                    $exportable = $post->isApproved() && (
                        $variant->status->canBeMarkedPosted()
                        || ($variant->status === SocialVariantStatus::Pending && in_array($status, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], true) && ! $account->canPublishDirectly())
                    );
                    $metricsState = $metrics->stateOf($variant);
                    $snapshot = $variant->latestSnapshot;
                @endphp
                <x-ui.card data-social-variant-card="{{ $variant->platform->value }}">
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <h2 class="flex-1 text-lg font-semibold text-fg" dir="auto">{{ $account->displayName() }}</h2>
                        <x-ui.badge :tone="$account->status->tone()">{{ $account->status->label() }}</x-ui.badge>
                        <x-ui.badge :tone="$variant->status->tone()" data-variant-status="{{ $variant->status->value }}">{{ $variant->status->label() }}</x-ui.badge>
                    </div>

                    <p class="rounded-lg bg-subtle p-3 text-sm whitespace-pre-line text-fg" dir="auto">{{ $composed !== '' ? $composed : __('No text yet') }}</p>
                    <p class="mt-1 text-xs {{ $length > $variant->platform->maxLength() ? 'text-fg-danger' : 'text-fg-tertiary' }}">{{ number_format($length) }} / {{ number_format($variant->platform->maxLength()) }}</p>
                    @if ($variant->first_comment)
                        <p class="mt-2 text-sm text-fg-secondary"><span class="font-semibold">{{ __('First comment') }}:</span> <span dir="auto">{{ $variant->first_comment }}</span></p>
                    @endif

                    <dl class="mt-4 grid grid-cols-1 gap-2 text-xs sm:grid-cols-3">
                        <div><dt class="text-fg-tertiary">{{ __('Attempts') }}</dt><dd class="text-fg">{{ $variant->attempts }}</dd></div>
                        <div><dt class="text-fg-tertiary">{{ __('Images') }}</dt><dd class="text-fg">{{ $variant->include_media ? $media->count() : 0 }}</dd></div>
                        <div><dt class="text-fg-tertiary">{{ __('Published') }}</dt><dd class="text-fg">{{ ($variant->published_at ?? $variant->exported_at)?->timezone($timezone)->translatedFormat('j M Y · H:i') ?? '—' }}</dd></div>
                    </dl>

                    @if ($variant->published_url)
                        <p class="mt-3 text-sm"><a href="{{ $variant->published_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-fg-brand hover:underline" dir="ltr">{{ __('View on :platform', ['platform' => $variant->platform->shortLabel()]) }} ↗</a></p>
                    @endif

                    @if ($variant->last_error && ! $variant->status->isDone())
                        <x-ui.alert :tone="$variant->status === SocialVariantStatus::Failed ? 'danger' : 'warning'" class="mt-4" :title="$variant->status === SocialVariantStatus::Failed ? __('Publishing failed') : __('Note')">
                            <span dir="auto">{{ $variant->last_error }}</span>
                        </x-ui.alert>
                    @endif

                    @if (in_array($variant->status, [SocialVariantStatus::Published, SocialVariantStatus::ExportedManually], true))
                        <div class="mt-4 rounded-lg border border-line p-3 text-sm" data-metrics-state="{{ $metricsState }}">
                            <p class="font-semibold text-fg">{{ __('Metrics') }} · <span class="font-normal text-fg-secondary">{{ \App\Services\Social\SocialMetrics::stateLabel($metricsState) }}</span></p>
                            @if ($snapshot)
                                <dl class="mt-2 grid grid-cols-3 gap-2 text-xs">
                                    <div><dt class="text-fg-tertiary">{{ __('Impressions') }}</dt><dd class="text-base font-semibold text-fg">{{ $snapshot->impressions !== null ? number_format($snapshot->impressions) : '—' }}</dd></div>
                                    <div><dt class="text-fg-tertiary">{{ __('Engagements') }}</dt><dd class="text-base font-semibold text-fg">{{ $snapshot->engagements !== null ? number_format($snapshot->engagements) : '—' }}</dd></div>
                                    <div><dt class="text-fg-tertiary">{{ __('Clicks') }}</dt><dd class="text-base font-semibold text-fg">{{ $snapshot->clicks !== null ? number_format($snapshot->clicks) : '—' }}</dd></div>
                                </dl>
                                <p class="mt-2 text-xs text-fg-tertiary">{{ __('Captured :date. “—” means the platform does not report it.', ['date' => $snapshot->captured_at->timezone($timezone)->translatedFormat('j M Y H:i')]) }}</p>
                            @elseif ($metricsState === \App\Services\Social\SocialMetrics::ERROR)
                                <p class="mt-1 text-xs text-fg-danger" dir="auto">{{ $variant->metrics_error }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($variant->status === SocialVariantStatus::Failed && $canReview && $account->canPublishDirectly() && ! $paused)
                        <form method="POST" action="{{ route('admin.social.variants.retry', $variant) }}" class="mt-4" data-confirm="{{ __('Check on :platform that the post is not live before retrying. Retry now?', ['platform' => $variant->platform->shortLabel()]) }}">
                            @csrf
                            <x-ui.button variant="secondary" size="sm">{{ __('Retry publishing') }}</x-ui.button>
                        </form>
                    @endif

                    @if ($exportable)
                        <section class="mt-5 rounded-lg border border-line-warning bg-warning-subtle/40 p-4" data-manual-export aria-labelledby="export-{{ $variant->id }}">
                            <h3 id="export-{{ $variant->id }}" class="text-sm font-semibold text-fg">{{ __('Manual export') }}</h3>
                            <p class="mt-1 text-xs text-fg-secondary">{{ __('Post this by hand on :platform, then paste the link of the live post. CodeFanz never reports a manual post as published until you do.', ['platform' => $variant->platform->label()]) }}</p>
                            <ol class="mt-3 flex flex-col gap-3 text-sm">
                                <li class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-fg">1.</span>
                                    <button type="button" data-copy="{{ $composed }}" data-copied-label="{{ $copied }}" class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">{{ __('Copy text') }}</button>
                                    @if ($variant->first_comment)
                                        <button type="button" data-copy="{{ $variant->first_comment }}" data-copied-label="{{ $copied }}" class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-fg hover:bg-subtle">{{ __('Copy first comment') }}</button>
                                    @endif
                                </li>
                                @if ($variant->include_media && $media->isNotEmpty())
                                    <li><span class="font-semibold text-fg">2.</span> {{ __('Download the images above (original or optimized) and add their alt text.') }}</li>
                                @endif
                                <li>
                                    <span class="font-semibold text-fg">{{ $variant->include_media && $media->isNotEmpty() ? '3.' : '2.' }}</span>
                                    {{ __('Suggested time: :date', ['date' => ($post->scheduled_at ?? $post->planned_at ?? now())->timezone($timezone)->translatedFormat('j M Y · H:i').' ('.$timezone.')']) }}
                                </li>
                            </ol>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('admin.social.variants.posted', $variant) }}" class="mt-4 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <div class="min-w-64 flex-1">
                                        <x-ui.input name="published_url" type="url" id="published-url-{{ $variant->id }}" :label="__('Link of the live post')" required maxlength="500" dir="ltr" :placeholder="$variant->platform->postUrlPattern()" />
                                    </div>
                                    <x-ui.button size="sm">{{ __('Mark as posted manually') }}</x-ui.button>
                                </form>
                            @endif
                        </section>
                    @endif
                </x-ui.card>
            @empty
                <x-ui.card><x-ui.empty-state :title="__('No accounts chosen')" :description="__('Edit the post and choose where to publish it.')" /></x-ui.card>
            @endforelse
        </div>

        <div class="flex flex-col gap-4">
            <x-ui.card :title="__('Workflow')">
                <ol class="mb-4 flex flex-wrap gap-1 text-xs" aria-label="{{ __('Workflow steps') }}">
                    @foreach ([SocialPostStatus::Draft, SocialPostStatus::InReview, SocialPostStatus::Approved, SocialPostStatus::Scheduled, SocialPostStatus::Published] as $step)
                        <li><x-ui.badge :tone="$step === $status ? $step->tone() : 'neutral'" :dot="$step === $status">{{ $step->label() }}</x-ui.badge></li>
                    @endforeach
                </ol>

                <div class="flex flex-col gap-3">
                    @if ($canEdit && $status->isEditable())
                        <x-ui.button variant="secondary" :href="route('admin.social.posts.edit', $post)">{{ __('Edit') }}</x-ui.button>
                    @endif

                    @if ($canEdit && $status->canBeSubmitted())
                        <form method="POST" action="{{ route('admin.social.posts.submit', $post) }}">
                            @csrf
                            <x-ui.button class="w-full">{{ __('Submit for approval') }}</x-ui.button>
                        </form>
                    @endif

                    @if ($generatedByMe && in_array($status, [SocialPostStatus::InReview, SocialPostStatus::Approved, SocialPostStatus::Scheduled], true))
                        <p class="text-sm text-fg-secondary" data-own-ai-post>{{ __('You generated this AI post, so another reviewer must approve and schedule it.') }}</p>
                    @endif

                    @if ($canReview && $status === SocialPostStatus::InReview)
                        <form method="POST" action="{{ route('admin.social.posts.approve', $post) }}" class="flex flex-col gap-3">
                            @csrf
                            <x-ui.input name="scheduled_for" type="datetime-local" id="approve-scheduled-for" :label="__('Schedule for (:zone, optional)', ['zone' => $timezone])" value="" />
                            <x-ui.button class="w-full">{{ __('Approve') }}</x-ui.button>
                        </form>
                    @endif

                    @if ($canReview && $status->canBeScheduled())
                        <form method="POST" action="{{ route('admin.social.posts.schedule', $post) }}" class="flex flex-col gap-3">
                            @csrf
                            <x-ui.input name="scheduled_for" type="datetime-local" id="schedule-for" :label="__('Publish at (:zone)', ['zone' => $timezone])" :value="$scheduleDefault" required />
                            <x-ui.button class="w-full">{{ $status === SocialPostStatus::Scheduled ? __('Reschedule') : __('Schedule') }}</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('admin.social.posts.publish-now', $post) }}" data-confirm="{{ __('Publish to every connected account now? Accounts without API access become ready for manual export.') }}">
                            @csrf
                            <x-ui.button variant="secondary" class="w-full" :disabled="$paused">{{ __('Publish now') }}</x-ui.button>
                        </form>
                        @if ($status === SocialPostStatus::Scheduled)
                            <form method="POST" action="{{ route('admin.social.posts.unschedule', $post) }}">
                                @csrf
                                <x-ui.button variant="ghost" class="w-full">{{ __('Remove schedule') }}</x-ui.button>
                            </form>
                        @endif
                    @endif

                    @if ($canReview && in_array($status, [SocialPostStatus::InReview, SocialPostStatus::Approved, SocialPostStatus::Scheduled], true))
                        <details class="rounded-lg border border-line p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-fg">{{ __('Request changes') }}</summary>
                            <form method="POST" action="{{ route('admin.social.posts.request-changes', $post) }}" class="mt-3 flex flex-col gap-3">
                                @csrf
                                <label for="note" class="sr-only">{{ __('Note for the author') }}</label>
                                <textarea id="note" name="note" rows="3" required minlength="3" maxlength="2000" dir="auto" placeholder="{{ __('What should change?') }}"
                                          class="w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg"></textarea>
                                <x-ui.button variant="secondary">{{ __('Send back to the author') }}</x-ui.button>
                            </form>
                        </details>
                    @endif

                    @if ($canEdit && $cancellable)
                        <form method="POST" action="{{ route('admin.social.posts.cancel', $post) }}" data-confirm="{{ __('Cancel this post? It will not be published.') }}">
                            @csrf
                            <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Cancel post') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('History')">
                @if ($audit->isEmpty())
                    <p class="text-sm text-fg-tertiary">{{ __('No history yet.') }}</p>
                @else
                    <ol class="flex flex-col gap-2 text-xs">
                        @foreach ($audit as $entry)
                            <li class="flex flex-col">
                                <span class="font-mono text-fg" dir="ltr">{{ $entry->operation }}</span>
                                <span class="text-fg-tertiary">{{ $entry->actor_label ?? __('System') }} · {{ $entry->created_at->timezone($timezone)->translatedFormat('j M H:i') }}@if ($entry->result !== 'success') · <span class="text-fg-danger">{{ $entry->result }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
