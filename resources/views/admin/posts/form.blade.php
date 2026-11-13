{{-- A11 Blog editor: one tab per language (stacked without JavaScript), editorial workflow, live checklist, settings. --}}
@php
    use App\Enums\PostStatus;

    $languages = \App\Models\Language::active();
    $user = auth()->user();
    $timezone = $user->timezone ?: config('app.timezone');
    $status = $post->status ?? PostStatus::Draft;
    $canReview = $post->exists && $user->can('review', $post);
    $warnings = \App\Services\Blog\EditorialChecklist::warningCount($checklist);
    $requirementsMet = \App\Services\Blog\EditorialChecklist::requirementsMet($checklist);
    $selectedTags = array_map('intval', (array) old('tag_ids', $post->tags->pluck('id')->all()));
    $coverId = old('cover_media_id', $post->coverImage()?->id);
    $manual = collect($checklist)->where('manual', true);
    $steps = PostStatus::trackerSteps();
    $currentStep = match ($status) {
        PostStatus::Draft, PostStatus::ChangesRequested => 0,
        PostStatus::InReview => 1,
        PostStatus::Approved => 2,
        PostStatus::Scheduled => 3,
        PostStatus::Published => 4,
        PostStatus::Archived => 5,
    };
    $mediaLibrary = $images->map(fn ($image) => [
        'id' => $image->id,
        'name' => $image->original_name,
        'url' => ($image->largestVariant('webp') ?? $image->largestVariant('gif'))?->url(),
        'thumb' => $image->variantsFor('webp')->first()?->url(),
        'alt' => $image->alt ?? [],
    ])->filter(fn ($image) => $image['url'] !== null)->values();
    $live = $post->exists && $post->isPubliclyVisible() ? ($post->exactTranslation(app()->getLocale()) ?? $post->translation()) : null;
    $scheduleDefault = old('scheduled_for', ($post->scheduled_for ?? now()->addDay()->setTime(9, 0))->timezone($timezone)->format('Y-m-d\TH:i'));
@endphp

<x-layouts.admin :title="$post->exists ? __('Edit article') : __('New article')" :crumb="__('Blog')">
    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('status') ?: $errors->first('acknowledge_warnings') ?: $errors->first('scheduled_for') ?: $errors->first('note') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($post->isAiOrigin() && $status !== PostStatus::Published)
        <x-ui.alert tone="warning" :title="__('AI-generated draft')" data-ai-draft-warning>
            {{ __('Created by AI. It can’t be published until a human reviewer other than its author checks every fact, completes the checklist and approves it.') }}
        </x-ui.alert>
    @endif

    @if ($status === PostStatus::ChangesRequested && filled($post->review_note))
        <x-ui.alert tone="warning" :title="__('Changes requested by :name', ['name' => $post->reviewer?->name ?? __('a reviewer')])">
            <span class="whitespace-pre-line" dir="auto">{{ $post->review_note }}</span>
        </x-ui.alert>
    @endif

    @if ($pendingChanges->isNotEmpty())
        <x-ui.alert tone="warning" :title="__('Pending AI/MCP proposals for this article')">
            {{ __('Saving here creates a new version, so these proposals will become stale and cannot be applied:') }}
            @foreach ($pendingChanges as $change)
                <a href="{{ route('admin.approvals.show', $change) }}" class="font-mono font-semibold underline">{{ $change->reference }}</a>
            @endforeach
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form id="post-form" method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}"
              class="flex min-w-0 flex-col gap-6" data-post-form data-checklist-url="{{ route('admin.posts.checklist') }}">
            @csrf
            @if ($post->exists)
                @method('PUT')
                <input type="hidden" name="lock_version" value="{{ $post->lock_version }}">
            @endif

            <x-ui.card data-tabs>
                <div role="tablist" aria-label="{{ __('Article languages') }}" class="mb-6 flex flex-wrap items-end gap-1 border-b border-line" data-tablist hidden>
                    @foreach ($languages as $code => $language)
                        <button type="button" role="tab" id="tab-{{ $code }}" aria-controls="panel-{{ $code }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-tab
                                class="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-semibold text-fg-secondary hover:text-fg aria-selected:border-brand aria-selected:text-fg-brand" lang="{{ $code }}">{{ $language['native_name'] }}</button>
                    @endforeach
                    @if ($post->exists)
                        <a href="{{ route('admin.posts.revisions.index', $post) }}" class="px-3 py-2 text-sm font-semibold text-fg-secondary hover:text-fg">{{ __('Revisions (:count)', ['count' => $revisionCount]) }}</a>
                    @endif
                    @if ($live)
                        <a href="{{ route('blog.show', ['locale' => $live->locale, 'slug' => $live->slug]) }}" target="_blank" rel="noopener" class="px-3 py-2 text-sm font-semibold text-fg-secondary hover:text-fg">{{ __('View live') }}</a>
                    @endif
                </div>

                @foreach ($languages as $code => $language)
                    @php($translation = $post->exactTranslation($code))
                    <section id="panel-{{ $code }}" role="tabpanel" aria-labelledby="tab-{{ $code }}" data-tab-panel data-locale="{{ $code }}" lang="{{ $code }}" dir="{{ $language['direction'] }}"
                             class="flex flex-col gap-5 [&+&]:mt-10 [&+&]:border-t [&+&]:border-line [&+&]:pt-10">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-fg-tertiary" data-panel-heading>{{ $language['native_name'] }}</h2>

                        <div class="flex flex-col gap-1.5">
                            <label for="title-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Title') }}</label>
                            <input id="title-{{ $code }}" name="translations[{{ $code }}][title]" value="{{ old("translations.{$code}.title", $translation?->title) }}" maxlength="200" data-field="title"
                                   class="w-full rounded-md border bg-surface px-3 py-2 text-2xl font-bold text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 lg:text-3xl {{ $errors->has("translations.{$code}.title") ? 'border-line-danger' : 'border-line-strong' }}"
                                   placeholder="{{ __('Article title') }}">
                            @error("translations.{$code}.title")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </div>

                        <x-ui.input :name="'translations['.$code.'][slug]'" :id="'slug-'.$code" :label="__('URL slug')" :value="$translation?->slug" maxlength="200" data-field="slug" :helper="url($code.'/blog').'/…'" />
                        @include('admin._textarea', ['name' => "translations[{$code}][excerpt]", 'id' => "excerpt-{$code}", 'label' => __('Excerpt'), 'value' => $translation?->excerpt, 'rows' => 2, 'max' => 400, 'help' => __('Shown on cards, in feeds and as the fallback meta description.')])

                        <div class="flex flex-col gap-1.5" data-rich-editor-field>
                            <label for="body-{{ $code }}" class="text-sm font-semibold text-fg">{{ __('Article body') }}</label>
                            <textarea id="body-{{ $code }}" name="translations[{{ $code }}][body]" rows="16" data-rich-editor data-field="body"
                                      data-labels="{{ json_encode([
                                          'toolbar' => __('Formatting'), 'bold' => __('Bold'), 'italic' => __('Italic'), 'h2' => __('Heading 2'), 'h3' => __('Heading 3'), 'h4' => __('Heading 4'),
                                          'bullet' => __('Bulleted list'), 'ordered' => __('Numbered list'), 'quote' => __('Quote'), 'code' => __('Code block'), 'callout' => __('Callout'),
                                          'table' => __('Insert table'), 'link' => __('Link'), 'image' => __('Image'), 'undo' => __('Undo'), 'redo' => __('Redo'),
                                          'linkPrompt' => __('Link address (https://… or /page). Leave empty to remove the link.'), 'linkInvalid' => __('Use an https:// address or a site path starting with /.'),
                                          'words' => __(':count words · :minutes min read'),
                                      ], JSON_UNESCAPED_UNICODE) }}"
                                      class="min-h-80 rounded-md border border-line-strong bg-surface px-3 py-2 font-mono text-sm text-fg">{{ old("translations.{$code}.body", $translation?->body) }}</textarea>
                            @error("translations.{$code}.body")<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            <p class="text-xs text-fg-tertiary" data-editor-stats>
                                @if ($translation)
                                    {{ __(':count words · :minutes min read', ['count' => $translation->word_count, 'minutes' => $translation->reading_minutes]) }}
                                @endif
                                <span data-editor-fallback-note>· {{ __('Without JavaScript you can edit the HTML directly. Unsafe markup is removed when you save.') }}</span>
                            </p>
                        </div>

                        <fieldset class="flex flex-col gap-4 rounded-lg border border-line-subtle p-4">
                            <legend class="px-1 text-sm font-semibold text-fg">{{ __('SEO') }}</legend>
                            <x-ui.input :name="'translations['.$code.'][focus_keyword]'" :id="'focus-keyword-'.$code" :label="__('Focus keyword')" :value="$translation?->focus_keyword" maxlength="100" data-field="focus_keyword" />
                            <x-ui.input :name="'translations['.$code.'][seo_title]'" :id="'seo-title-'.$code" :label="__('SEO title')" :value="$translation?->seo_title" maxlength="70" :helper="__('Up to 70 characters. Defaults to the title.')" />
                            <x-ui.input :name="'translations['.$code.'][seo_description]'" :id="'seo-description-'.$code" :label="__('Meta description')" :value="$translation?->seo_description" maxlength="170" :helper="__('Aim for 50–160 characters.')" />
                            <x-ui.input :name="'translations['.$code.'][canonical_url]'" :id="'canonical-'.$code" :label="__('Canonical URL (optional)')" :value="$translation?->canonical_url" maxlength="500" dir="ltr" :helper="__('Only when this article was first published elsewhere.')" />
                        </fieldset>
                    </section>
                @endforeach
            </x-ui.card>

            <script type="application/json" data-media-library>@json($mediaLibrary, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
        </form>

        <div class="flex flex-col gap-6">
            <x-ui.card :title="__('Editorial workflow')">
                <div class="flex flex-col gap-4">
                    <ol class="flex flex-col gap-2 text-sm" aria-label="{{ __('Workflow progress') }}">
                        @foreach ($steps as $index => $step)
                            @php($state = $index < $currentStep ? 'done' : ($index === $currentStep ? 'current' : 'todo'))
                            <li class="flex items-center gap-2 {{ $state === 'current' ? 'font-semibold text-fg' : ($state === 'done' ? 'text-fg' : 'text-fg-tertiary') }}" @if ($state === 'current') aria-current="step" @endif>
                                @if ($state === 'done')
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-fg-success" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>
                                @elseif ($state === 'current')
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-fg-warning" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/></svg>
                                @else
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17"/></svg>
                                @endif
                                {{ $step === PostStatus::Draft && $status === PostStatus::ChangesRequested ? PostStatus::ChangesRequested->label() : $step->label() }}
                                @if ($step === PostStatus::Draft && $post->isAiOrigin())
                                    <span class="text-xs text-fg-tertiary">({{ __('AI draft') }})</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>

                    <p class="text-sm text-fg-secondary">
                        {{ __('Status') }}: <x-ui.badge :tone="$status->tone()">{{ $status->label() }}</x-ui.badge>
                        @if ($post->exists) · {{ __('Version :version', ['version' => $post->lock_version]) }} @endif
                    </p>
                    @if ($status === PostStatus::Scheduled && $post->scheduled_for)
                        <p class="text-sm text-fg">{{ __('Goes live :date', ['date' => $post->scheduled_for->timezone($timezone)->translatedFormat('j M Y, H:i').' ('.$timezone.')']) }}</p>
                    @endif
                    @if ($post->published_at && $status === PostStatus::Published)
                        <p class="text-sm text-fg-secondary">{{ __('Published :date', ['date' => $post->published_at->timezone($timezone)->translatedFormat('j M Y, H:i')]) }}</p>
                    @endif

                    <x-ui.button form="post-form">{{ $post->exists ? __('Save changes') : __('Create draft') }}</x-ui.button>
                    @if ($post->exists && $status->isApprovalLocked())
                        <p class="text-xs text-fg-tertiary">{{ __('Changing the content withdraws the approval and returns the article to draft.') }}</p>
                    @endif

                    @if ($post->exists)
                        @if ($status->canBeSubmitted())
                            @can('submit', $post)
                                <form method="POST" action="{{ route('admin.posts.submit', $post) }}">
                                    @csrf
                                    <x-ui.button variant="secondary" class="w-full" :disabled="! $requirementsMet">{{ __('Submit for review') }}</x-ui.button>
                                    @unless ($requirementsMet)
                                        <p class="mt-1 text-xs text-fg-tertiary">{{ __('Save the default-language title, slug and excerpt first.') }}</p>
                                    @endunless
                                </form>
                            @endcan
                        @endif

                        @if (in_array($status, [PostStatus::InReview, PostStatus::Approved, PostStatus::Scheduled], true) && ! $canReview)
                            <p class="rounded-md bg-subtle p-3 text-sm text-fg-secondary">
                                {{ $post->isAiOrigin() && $post->author_id === $user->id ? __('You are the author of this AI draft, so another reviewer must approve it.') : __('Waiting for a reviewer with approval permission.') }}
                            </p>
                        @endif

                        @if ($canReview)
                            @php($acknowledge = $warnings > 0)
                            @if ($status === PostStatus::InReview)
                                <form method="POST" action="{{ route('admin.posts.approve', $post) }}" class="flex flex-col gap-3 border-t border-line-subtle pt-4">
                                    @csrf
                                    <x-ui.select name="then" id="approve-then" :label="__('After approval')" value="schedule"
                                                 :options="['none' => __('Keep approved'), 'publish' => __('Publish now'), 'schedule' => __('Schedule')]" />
                                    <x-ui.input name="scheduled_for" id="approve-scheduled-for" type="datetime-local" :label="__('Schedule for')" :value="$scheduleDefault" :helper="__('Time zone: :zone', ['zone' => $timezone])" />
                                    @include('admin.posts._acknowledge', ['id' => 'approve-ack', 'show' => $acknowledge, 'warnings' => $warnings])
                                    <x-ui.button class="w-full">{{ __('Approve') }}</x-ui.button>
                                </form>
                            @endif

                            @if (in_array($status, [PostStatus::Approved, PostStatus::Scheduled], true))
                                <form method="POST" action="{{ route('admin.posts.publish', $post) }}" class="flex flex-col gap-3 border-t border-line-subtle pt-4">
                                    @csrf
                                    @include('admin.posts._acknowledge', ['id' => 'publish-ack', 'show' => $acknowledge, 'warnings' => $warnings])
                                    <x-ui.button class="w-full">{{ __('Publish now') }}</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.posts.schedule', $post) }}" class="flex flex-col gap-3 border-t border-line-subtle pt-4">
                                    @csrf
                                    <x-ui.input name="scheduled_for" id="schedule-for" type="datetime-local" :label="__('Schedule for')" :value="$scheduleDefault" :helper="__('Time zone: :zone', ['zone' => $timezone])" />
                                    @include('admin.posts._acknowledge', ['id' => 'schedule-ack', 'show' => $acknowledge, 'warnings' => $warnings])
                                    <x-ui.button variant="secondary" class="w-full">{{ $status === PostStatus::Scheduled ? __('Reschedule') : __('Schedule') }}</x-ui.button>
                                </form>
                            @endif

                            @if ($status === PostStatus::Scheduled)
                                <form method="POST" action="{{ route('admin.posts.unschedule', $post) }}">
                                    @csrf
                                    <x-ui.button variant="ghost" class="w-full">{{ __('Cancel schedule') }}</x-ui.button>
                                </form>
                            @endif

                            @if (in_array($status, [PostStatus::InReview, PostStatus::Approved, PostStatus::Scheduled], true))
                                <form method="POST" action="{{ route('admin.posts.request-changes', $post) }}" class="flex flex-col gap-3 border-t border-line-subtle pt-4">
                                    @csrf
                                    @include('admin._textarea', ['name' => 'note', 'id' => 'review-note', 'label' => __('Note for the author'), 'value' => null, 'rows' => 3, 'max' => 2000])
                                    <x-ui.button variant="secondary" class="w-full">{{ __('Request changes') }}</x-ui.button>
                                </form>
                            @endif

                            @if ($status !== PostStatus::Archived && $status !== PostStatus::InReview)
                                <form method="POST" action="{{ route('admin.posts.archive', $post) }}" data-confirm="{{ __('Archive this article? It will no longer be visible on the site.') }}" data-confirm-label="{{ __('Archive') }}">
                                    @csrf
                                    <x-ui.button variant="ghost" class="w-full text-fg-danger">{{ __('Archive') }}</x-ui.button>
                                </form>
                            @endif
                        @endif

                        @if ($status === PostStatus::Archived)
                            <form method="POST" action="{{ route('admin.posts.reopen', $post) }}">
                                @csrf
                                <x-ui.button variant="secondary" class="w-full">{{ __('Move back to draft') }}</x-ui.button>
                            </form>
                        @endif

                        @if ($live)
                            <a href="{{ route('blog.show', ['locale' => $live->locale, 'slug' => $live->slug]) }}" class="text-sm font-semibold text-fg-brand hover:underline" target="_blank" rel="noopener">{{ __('View live article') }}</a>
                        @endif
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Editorial checklist')">
                <div class="flex flex-col gap-4">
                    <div data-checklist aria-live="polite">
                        @include('admin.posts._checklist', ['checklist' => $checklist])
                    </div>
                    <fieldset class="flex flex-col gap-2 border-t border-line-subtle pt-4">
                        <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Confirmed by the editor') }}</legend>
                        @foreach ($manual as $item)
                            <label class="flex items-start gap-2.5 text-sm text-fg">
                                <input type="hidden" name="checklist[{{ $item['key'] }}]" value="0" form="post-form">
                                <input type="checkbox" name="checklist[{{ $item['key'] }}]" value="1" form="post-form" @checked(old("checklist.{$item['key']}", $item['passed'])) class="mt-0.5 size-4 accent-brand" data-checklist-input>
                                {{ $item['label'] }}
                            </label>
                        @endforeach
                        <p class="text-xs text-fg-tertiary">{{ __('Saved with the article. Only the default-language title, slug and excerpt are required; open items are warnings reviewers must acknowledge.') }}</p>
                    </fieldset>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Article settings')">
                <div class="flex flex-col gap-4">
                    <x-ui.select name="post_category_id" form="post-form" :label="__('Category')" :value="$post->post_category_id" :placeholder="__('No category')"
                                 :options="$categories->mapWithKeys(fn ($category) => [$category->id => $category->translation()?->name ?? '#'.$category->id])->all()" />
                    @if ($tags->isNotEmpty())
                        <fieldset>
                            <legend class="mb-2 text-sm font-semibold text-fg">{{ __('Tags') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($tags as $tag)
                                    <label class="inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-sm text-fg has-checked:border-line-brand has-checked:bg-brand-subtle">
                                        <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" form="post-form" @checked(in_array($tag->id, $selectedTags, true)) class="size-4 accent-brand">
                                        {{ $tag->translation()?->name ?? '#'.$tag->id }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif
                    <x-ui.select name="cover_media_id" form="post-form" :label="__('Cover image')" :value="$coverId" :placeholder="__('No cover image')"
                                 :options="$images->mapWithKeys(fn ($image) => [$image->id => $image->original_name])->all()"
                                 :helper="__('Only optimized images are listed. Upload new images in the media library.')" />
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Featured article') }}</span>
                        <input type="checkbox" name="is_featured" value="1" form="post-form" @checked(old('is_featured', $post->is_featured)) class="size-4 accent-brand">
                    </label>
                    <label class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-fg">{{ __('Published in one language only') }}</span>
                        <input type="checkbox" name="is_single_language" value="1" form="post-form" @checked(old('is_single_language', $post->is_single_language)) class="size-4 accent-brand" data-checklist-input>
                    </label>
                    @if ($post->exists)
                        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 border-t border-line-subtle pt-4 text-sm">
                            <dt class="text-fg-tertiary">{{ __('Author') }}</dt><dd class="text-fg">{{ $post->author?->name ?? '—' }}</dd>
                            <dt class="text-fg-tertiary">{{ __('Origin') }}</dt><dd class="text-fg">{{ $post->origin->label() }}</dd>
                            @if ($post->reviewer)
                                <dt class="text-fg-tertiary">{{ __('Reviewer') }}</dt><dd class="text-fg">{{ $post->reviewer->name }}</dd>
                            @endif
                        </dl>
                        <a href="{{ route('admin.posts.revisions.index', $post) }}" class="text-sm font-semibold text-fg-brand hover:underline">{{ __('Revision history (:count)', ['count' => $revisionCount]) }}</a>
                    @endif
                </div>
            </x-ui.card>

            @if ($post->exists)
                @include('admin._trash-card', ['action' => route('admin.posts.destroy', $post), 'confirm' => __('Move this article to trash? It will no longer be visible on the site.'), 'label' => __('Move to trash')])
            @endif
        </div>
    </div>

    <dialog data-media-picker class="m-auto w-full max-w-2xl rounded-2xl bg-surface-raised p-6 text-fg shadow-xl backdrop:bg-ink-950/50" aria-labelledby="media-picker-title">
        <form method="dialog" class="flex flex-col gap-4">
            <h2 id="media-picker-title" class="text-lg font-semibold">{{ __('Insert an image from the media library') }}</h2>
            @if ($mediaLibrary->isEmpty())
                <p class="text-sm text-fg-secondary">{{ __('No optimized images yet. Upload images in the media library first.') }}</p>
            @else
                <ul class="grid max-h-80 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4" data-media-picker-list></ul>
                <div class="flex flex-col gap-1.5">
                    <label for="media-picker-alt" class="text-sm font-semibold text-fg">{{ __('Alt text') }}</label>
                    <input id="media-picker-alt" type="text" maxlength="300" data-media-picker-alt class="rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-fg">
                    <p class="text-xs text-fg-tertiary">{{ __('Describe the image for people who cannot see it.') }}</p>
                </div>
            @endif
            <div class="flex justify-end gap-3">
                <x-ui.button variant="secondary" value="cancel">{{ __('Cancel') }}</x-ui.button>
                @if ($mediaLibrary->isNotEmpty())
                    <x-ui.button value="insert" data-media-picker-insert>{{ __('Insert image') }}</x-ui.button>
                @endif
            </div>
        </form>
    </dialog>
</x-layouts.admin>
