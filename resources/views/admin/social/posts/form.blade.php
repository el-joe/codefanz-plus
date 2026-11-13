{{-- B06 composer: one post, one variant per account with live limits and previews, library images with alt text,
     UTM builder and "Generate with AI" (Content Studio social_post template through AiGateway). --}}
@php
    use App\Enums\SocialPostStatus;

    $languages = \App\Models\Language::active();
    $timezone = auth()->user()->timezone ?: config('app.timezone');
    $variants = $post->exists ? $post->variants->keyBy('social_account_id') : collect();
    $oldVariants = old('variants');
    $selectedMedia = array_map('intval', (array) old('media_ids', $selectedMedia));
    $mediaAlt = (array) old('media_alt', $post->media_alt ?? []);
    $utm = (array) old('utm', $post->utm ?? ['medium' => 'social']);
    $planned = old('planned_for', $post->planned_at?->timezone($timezone)->format('Y-m-d\TH:i'));
    $approvalLocked = $post->exists && in_array($post->status, [SocialPostStatus::Approved, SocialPostStatus::Scheduled], true);
@endphp

<x-layouts.admin :title="$post->exists ? __('Edit social post') : __('New social post')" :crumb="__('Social')">
    @include('admin.social._tabs')

    @if ($errors->any())
        <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first('lock_version') ?: $errors->first('status') ?: $errors->first('ai') ?: $errors->first('variants') ?: __('Some fields need attention.') }}</x-ui.alert>
    @endif

    @if ($post->isAiOrigin())
        <x-ui.alert tone="warning" :title="__('AI draft')">{{ __('This copy was written with AI. Check every fact, number and link. Someone other than the person who generated it must approve it.') }}</x-ui.alert>
    @endif

    @if ($approvalLocked)
        <x-ui.alert tone="warning" :title="__('Saving sends the post back to draft')">{{ __('This post is approved. Any change needs a new approval before it can be published.') }}</x-ui.alert>
    @endif

    <form id="social-form" method="POST" action="{{ $post->exists ? route('admin.social.posts.update', $post) : route('admin.social.posts.store') }}"
          class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]" data-social-composer>
        @csrf
        @if ($post->exists)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $post->lock_version }}">
        @endif

        <div class="flex min-w-0 flex-col gap-6">
            <x-ui.card :title="__('Post')">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <x-ui.input name="title" :label="__('Internal title')" :value="$post->title" required maxlength="150" :helper="__('Only shown inside the admin and on the calendar.')" />
                    </div>
                    <x-ui.select name="locale" :label="__('Language of the copy')" :value="$post->locale" :options="$languages->map(fn ($language) => $language['native_name'])->all()" />
                    <div class="flex flex-col gap-1.5">
                        <x-ui.input name="campaign" :label="__('Campaign')" :value="$post->campaign" maxlength="80" list="social-campaigns" :helper="__('Groups posts in filters and the calendar.')" />
                        <datalist id="social-campaigns">
                            @foreach ($campaigns as $campaign)
                                <option value="{{ $campaign }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <x-ui.input name="planned_for" type="datetime-local" :label="__('Planned time (:zone)', ['zone' => $timezone])" :value="$planned"
                                :helper="__('A suggestion for the calendar. The post is only scheduled after approval.')" />
                    <x-ui.input name="link_url" type="url" :label="__('Link (https)')" :value="$post->link_url" maxlength="500" dir="ltr" data-social-link placeholder="https://" />
                </div>

                <fieldset class="mt-6 rounded-lg border border-line p-4">
                    <legend class="px-1 text-sm font-semibold text-fg">{{ __('UTM builder') }}</legend>
                    <p class="mb-3 text-xs text-fg-tertiary">{{ __('utm_source is set to each platform automatically. Letters, numbers, dots, dashes and underscores only.') }}</p>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <x-ui.input name="utm[medium]" id="utm_medium" :label="__('Medium')" :value="$utm['medium'] ?? ''" maxlength="80" dir="ltr" data-social-utm="medium" />
                        <x-ui.input name="utm[campaign]" id="utm_campaign" :label="__('Campaign tag')" :value="$utm['campaign'] ?? ''" maxlength="80" dir="ltr" data-social-utm="campaign" />
                        <x-ui.input name="utm[content]" id="utm_content" :label="__('Content tag')" :value="$utm['content'] ?? ''" maxlength="80" dir="ltr" data-social-utm="content" />
                    </div>
                </fieldset>
            </x-ui.card>

            <x-ui.card :title="__('Publish to')">
                @if ($accounts->isEmpty())
                    <x-ui.empty-state :title="__('No social accounts yet')" :description="__('Connect an account or add a manual one first.')">
                        <x-ui.button variant="secondary" :href="route('admin.social.accounts.index')">{{ __('Manage accounts') }}</x-ui.button>
                    </x-ui.empty-state>
                @else
                    <div class="flex flex-col gap-4">
                        @foreach ($accounts as $account)
                            @php
                                $platform = $account->platform;
                                $saved = $variants->get($account->id);
                                $input = is_array($oldVariants) ? ($oldVariants[$account->id] ?? []) : null;
                                $enabled = $input !== null ? (bool) ($input['enabled'] ?? false) : ($saved !== null);
                                $text = $input['text'] ?? $saved?->text ?? '';
                                $hashtags = $input['hashtags'] ?? $saved?->hashtags ?? '';
                                $firstComment = $input['first_comment'] ?? $saved?->first_comment ?? '';
                                $includeMedia = $input !== null ? (bool) ($input['include_media'] ?? false) : ($saved?->include_media ?? true);
                                $key = 'variants.'.$account->id;
                            @endphp
                            <fieldset class="rounded-lg border border-line p-4 has-[[data-social-enabled]:checked]:border-line-brand" data-social-variant
                                      data-platform="{{ $platform->value }}" data-max="{{ $platform->maxLength() }}" data-link-placement="{{ $platform->linkPlacement() }}"
                                      data-link-weight="{{ $platform->linkWeight() ?? '' }}" data-utm-source="{{ $platform->utmSource() }}" data-account="{{ $account->id }}">
                                <legend class="sr-only">{{ $account->displayName() }}</legend>
                                <label class="flex flex-wrap items-center gap-3">
                                    <input type="checkbox" name="variants[{{ $account->id }}][enabled]" value="1" @checked($enabled) class="size-4 accent-brand" data-social-enabled>
                                    <span class="font-semibold text-fg" dir="auto">{{ $account->displayName() }}</span>
                                    <x-ui.badge :tone="$account->status->tone()">{{ $account->status->label() }}</x-ui.badge>
                                </label>
                                <div class="mt-4 flex flex-col gap-4" data-social-fields>
                                    <div class="flex flex-col gap-1.5">
                                        <label for="variant-{{ $account->id }}-text" class="text-sm font-semibold text-fg">{{ __(':platform text', ['platform' => $platform->label()]) }}</label>
                                        <textarea id="variant-{{ $account->id }}-text" name="variants[{{ $account->id }}][text]" rows="5" dir="auto" data-social-text
                                                  aria-describedby="variant-{{ $account->id }}-count"
                                                  @error($key.'.text') aria-invalid="true" @enderror
                                                  class="w-full rounded-md border bg-surface px-3 py-2.5 text-base text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20 {{ $errors->has($key.'.text') ? 'border-line-danger' : 'border-line-strong' }}">{{ $text }}</textarea>
                                        <p class="flex justify-between gap-3 text-xs text-fg-tertiary">
                                            <span>{{ __('Counted with hashtags and link, as :platform counts them.', ['platform' => $platform->shortLabel()]) }}</span>
                                            <span id="variant-{{ $account->id }}-count" data-social-count aria-live="polite">{{ number_format($platform->maxLength()) }}</span>
                                        </p>
                                        @error($key.'.text')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <x-ui.input name="variants[{{ $account->id }}][hashtags]" id="variant-{{ $account->id }}-hashtags" :label="__('Hashtags')" :value="$hashtags" maxlength="1000" dir="auto" data-social-hashtags
                                                    :helper="$platform->maxHashtags() ? __('Space separated, up to :max.', ['max' => $platform->maxHashtags()]) : __('Space separated.')" />
                                        @if ($platform->supportsFirstComment())
                                            <x-ui.input name="variants[{{ $account->id }}][first_comment]" id="variant-{{ $account->id }}-first_comment" :label="__('First comment (optional)')" :value="$firstComment" maxlength="1000" dir="auto" />
                                        @endif
                                    </div>
                                    <label class="flex items-start gap-3 text-sm">
                                        <input type="hidden" name="variants[{{ $account->id }}][include_media]" value="0">
                                        <input type="checkbox" name="variants[{{ $account->id }}][include_media]" value="1" @checked($includeMedia) class="mt-0.5 size-4 accent-brand" data-social-include-media>
                                        <span>
                                            <span class="font-semibold text-fg">{{ __('Attach the selected images') }}</span>
                                            <span class="block text-xs text-fg-tertiary">{{ $platform->mediaHint() }}</span>
                                        </span>
                                    </label>
                                    @error($key.'.include_media')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                                    @error($key.'.hashtags')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                                    @if (! $account->canPublishDirectly())
                                        <p class="text-xs text-fg-warning">{{ __('This account cannot publish through the API. At the scheduled time the post is prepared for manual export.') }}</p>
                                    @endif
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Images from the media library')">
                @if ($images->isEmpty())
                    <p class="text-sm text-fg-secondary">{{ __('No optimized images yet. Upload images in the media library first.') }}</p>
                @else
                    <p class="mb-4 text-xs text-fg-tertiary">{{ __('Selected images go out in this order with every variant that attaches images. Alt text is sent where the platform supports it.') }}</p>
                    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($images as $image)
                            @php
                                $thumb = $image->variantsFor('webp')->first()?->url();
                            @endphp
                            <li class="group flex flex-col gap-2 rounded-lg border border-line p-2 has-checked:border-line-brand has-checked:bg-brand-subtle">
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" name="media_ids[]" value="{{ $image->id }}" @checked(in_array($image->id, $selectedMedia, true)) class="size-4 shrink-0 accent-brand" data-social-media
                                           data-thumb="{{ $thumb }}" data-ratio="{{ $image->width && $image->height ? round($image->width / $image->height, 2) : '' }}">
                                    @if ($thumb)
                                        <img src="{{ $thumb }}" alt="" class="size-12 shrink-0 rounded object-cover" loading="lazy">
                                    @endif
                                    <span class="min-w-0 text-xs">
                                        <span class="block truncate font-semibold text-fg">{{ $image->original_name }}</span>
                                        <span class="text-fg-tertiary">{{ $image->width }}×{{ $image->height }} · {{ \Illuminate\Support\Str::after($image->mime_type, 'image/') }}</span>
                                    </span>
                                </label>
                                <label class="hidden flex-col gap-1 text-xs group-has-checked:flex">
                                    <span class="font-semibold text-fg">{{ __('Alt text') }}</span>
                                    <input type="text" name="media_alt[{{ $image->id }}]" value="{{ $mediaAlt[$image->id] ?? $mediaAlt[(string) $image->id] ?? $image->altText($post->locale) }}" maxlength="1000" dir="auto"
                                           class="rounded-md border border-line-strong bg-surface px-2 py-1.5 text-sm text-fg">
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    @error('media_ids.*')<p class="mt-2 text-sm text-fg-danger">{{ $message }}</p>@enderror
                @endif
            </x-ui.card>

            @if ($canUseAi)
                <x-ui.card :title="__('Generate with AI')">
                    <p class="mb-3 text-sm text-fg-secondary">{{ __('Writes a version per chosen platform with the social post template and your brand voice. The result is a draft you edit; it is never published without approval.') }}</p>
                    @if ($aiReason)
                        <x-ui.alert tone="warning" data-social-ai-unavailable>{{ $aiReason->label() }}</x-ui.alert>
                    @else
                        <div class="flex flex-col gap-1.5">
                            <label for="topic" class="text-sm font-semibold text-fg">{{ __('What is the post about?') }}</label>
                            <textarea id="topic" name="topic" rows="3" maxlength="1000" dir="auto"
                                      class="w-full rounded-md border bg-surface px-3 py-2.5 text-base text-fg {{ $errors->has('topic') ? 'border-line-danger' : 'border-line-strong' }}">{{ old('topic') }}</textarea>
                            @error('topic')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                            @error('ai')<p class="text-sm text-fg-danger">{{ $message }}</p>@enderror
                        </div>
                        <div class="mt-4">
                            <x-ui.button variant="secondary" name="ai" value="1">{{ __('Save and generate with AI') }}</x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            <div class="flex flex-wrap gap-3">
                <x-ui.button>{{ $post->exists ? __('Save changes') : __('Save draft') }}</x-ui.button>
                <x-ui.button variant="secondary" :href="$post->exists ? route('admin.social.posts.show', $post) : route('admin.social.posts.index')">{{ __('Cancel') }}</x-ui.button>
            </div>
        </div>

        <aside class="flex flex-col gap-4" aria-label="{{ __('Previews') }}">
            <p class="text-sm font-semibold text-fg">{{ __('Preview') }}</p>
            @foreach ($accounts as $account)
                @php($previewShown = is_array($oldVariants) ? (bool) ($oldVariants[$account->id]['enabled'] ?? false) : $variants->has($account->id))
                <x-ui.card padding="p-4" data-social-preview="{{ $account->id }}" :class="$previewShown ? '' : 'hidden'">
                    <p class="mb-3 text-xs text-fg-tertiary">{{ __('Preview') }} · {{ $account->platform->label() }}</p>
                    <div class="rounded-lg border border-line p-3">
                        <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-fg">
                            <span class="flex size-8 items-center justify-center rounded-md bg-brand-subtle text-xs text-fg-brand" aria-hidden="true">{{ mb_substr($account->name, 0, 2) }}</span>
                            <span dir="auto">{{ $account->name }}</span>
                        </p>
                        <p class="text-sm whitespace-pre-line text-fg" dir="auto" data-social-preview-text>{{ $variants->get($account->id)?->composedText($post) ?? '' }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-1" data-social-preview-media></div>
                    </div>
                </x-ui.card>
            @endforeach
            <p class="text-xs text-fg-tertiary">{{ __('Previews are approximate. Each platform decides the final layout, link cards and truncation.') }}</p>
        </aside>
    </form>
</x-layouts.admin>
