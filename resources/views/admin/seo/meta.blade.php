{{-- A12 · Meta editor: every indexable item per language, with inline editing of its SEO fields only. --}}
@php
    $languages = \App\Models\Language::active();
    $field = 'w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-fg focus:border-line-focus focus:outline-none focus:ring-4 focus:ring-brand/20';
    $scoreTone = fn (int $score): string => $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
@endphp

<x-layouts.admin :title="__('SEO meta editor')" :crumb="__('Content & SEO')">
    @include('admin.seo._tabs')

    <x-ui.alert tone="info" :title="__('Scores guide quality — they don’t guarantee rankings')">
        {{ __('Each score adds up fixed checks on the title, description, focus keyword and indexability. Only SEO fields are edited here; every save is versioned and audited.') }}
        @if ($averageScore !== null)
            <span class="font-semibold">{{ __('Average score for this view: :score/100.', ['score' => $averageScore]) }}</span>
        @endif
    </x-ui.alert>

    <form method="GET" action="{{ route('admin.seo.meta') }}" data-auto-submit class="flex flex-wrap items-end gap-3" role="search">
        <div class="w-48"><x-ui.select name="type" :label="__('Type')" :options="$types" :value="$filters['type'] ?? null" :placeholder="__('All types')" /></div>
        <div class="w-40"><x-ui.select name="locale" :label="__('Language')" :options="$languages->map(fn ($language) => $language['native_name'])->all()" :value="$filters['locale'] ?? null" :placeholder="__('All languages')" /></div>
        <div class="w-40"><x-ui.select name="score" :label="__('Score')" :options="['low' => __('Below 50'), 'medium' => __('50–79'), 'high' => __('80 and above')]" :value="$filters['score'] ?? null" :placeholder="__('Any score')" /></div>
        <div class="min-w-60 flex-1"><x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" maxlength="100" /></div>
        <x-ui.button variant="secondary">{{ __('Filter') }}</x-ui.button>
    </form>

    @if ($rows->isEmpty())
        <x-ui.card><x-ui.empty-state :title="__('Nothing matches these filters')" /></x-ui.card>
    @else
        <ul class="flex flex-col gap-3">
            @foreach ($rows as $row)
                @php
                    $isOld = old('row_key') === $row['key'];
                    $value = fn (string $key, mixed $default) => $isOld ? old($key, $default) : $default;
                    $score = $row['score']['score'];
                    $direction = $languages[$row['locale']]['direction'] ?? 'ltr';
                @endphp
                <li id="{{ $row['key'] }}" class="rounded-xl border border-line bg-surface" data-seo-row="{{ $row['key'] }}">
                    <div class="flex flex-wrap items-start gap-4 p-4">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-fg" dir="auto">{{ $row['name'] }}</span>
                                <x-ui.badge :dot="false">{{ $row['type_label'] }}</x-ui.badge>
                                <x-ui.badge tone="brand" :dot="false">{{ strtoupper($row['locale']) }}</x-ui.badge>
                            </p>
                            <a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="mt-1 block break-all font-mono text-xs text-fg-brand hover:underline" dir="ltr">{{ rawurldecode((string) parse_url($row['url'], PHP_URL_PATH)) }}</a>
                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-xs text-fg-secondary sm:grid-cols-2 lg:grid-cols-4">
                                <div><dt class="inline">{{ __('Title') }}:</dt> <dd class="inline">{{ trans_choice('{0} empty|{1} 1 character|[2,*] :count characters', mb_strlen((string) $row['effective_title']), ['count' => mb_strlen((string) $row['effective_title'])]) }}</dd></div>
                                <div><dt class="inline">{{ __('Description') }}:</dt> <dd class="inline">{{ trans_choice('{0} empty|{1} 1 character|[2,*] :count characters', mb_strlen((string) $row['effective_description']), ['count' => mb_strlen((string) $row['effective_description'])]) }}</dd></div>
                                <div><dt class="inline">{{ __('Focus keyword') }}:</dt> <dd class="inline" dir="auto">{{ $row['focus_keyword'] ?: '—' }}</dd></div>
                                <div><dt class="inline">{{ __('Canonical') }}:</dt> <dd class="inline">{{ $row['canonical_url'] ? __('Custom') : __('Self') }}</dd></div>
                            </dl>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <x-ui.badge :tone="$scoreTone($score)" data-score="{{ $score }}">{{ __('Score :score/100', ['score' => $score]) }}</x-ui.badge>
                            @if ($row['indexable'])
                                <x-ui.badge tone="success" :dot="false">{{ __('Indexable') }}</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral" :dot="false">{{ __('Not indexed') }} · {{ $row['reason'] }}</x-ui.badge>
                            @endif
                        </div>
                    </div>

                    <details class="border-t border-line-subtle" @if ($isOld) open @endif>
                        <summary class="cursor-pointer px-4 py-2.5 text-sm font-semibold text-fg-brand hover:bg-subtle">{{ __('Edit SEO') }}</summary>
                        <div class="grid grid-cols-1 gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <form method="POST" action="{{ route('admin.seo.meta.update') }}" class="flex flex-col gap-4" lang="{{ $row['locale'] }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="row_key" value="{{ $row['key'] }}">
                                <input type="hidden" name="type" value="{{ $row['type'] }}">
                                <input type="hidden" name="target" value="{{ $row['id'] ?? $row['route_key'] }}">
                                <input type="hidden" name="locale" value="{{ $row['locale'] }}">
                                <input type="hidden" name="version" value="{{ $row['version'] }}">

                                @if ($isOld && $errors->any())
                                    <x-ui.alert tone="danger" :title="__('Please fix the highlighted fields')">{{ $errors->first() }}</x-ui.alert>
                                @endif

                                <div class="flex flex-col gap-1.5">
                                    <label for="{{ $row['key'] }}-title" class="text-sm font-semibold text-fg">{{ __('SEO title') }}</label>
                                    <input id="{{ $row['key'] }}-title" name="seo_title" maxlength="70" dir="{{ $direction }}" value="{{ $value('seo_title', $row['seo_title']) }}" placeholder="{{ $row['effective_title'] }}" data-char-count="60" class="{{ $field }}">
                                    <p class="text-xs text-fg-tertiary" data-char-count-output>{{ __('Recommended 30–60 characters. Blank uses the page title.') }}</p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label for="{{ $row['key'] }}-description" class="text-sm font-semibold text-fg">{{ __('Meta description') }}</label>
                                    <textarea id="{{ $row['key'] }}-description" name="seo_description" maxlength="170" rows="2" dir="{{ $direction }}" placeholder="{{ $row['effective_description'] }}" data-char-count="160" class="{{ $field }}">{{ $value('seo_description', $row['seo_description']) }}</textarea>
                                    <p class="text-xs text-fg-tertiary" data-char-count-output>{{ __('Recommended 70–160 characters.') }}</p>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <label for="{{ $row['key'] }}-keyword" class="text-sm font-semibold text-fg">{{ __('Focus keyword') }}</label>
                                        <input id="{{ $row['key'] }}-keyword" name="focus_keyword" maxlength="100" dir="{{ $direction }}" value="{{ $value('focus_keyword', $row['focus_keyword']) }}" class="{{ $field }}">
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label for="{{ $row['key'] }}-canonical" class="text-sm font-semibold text-fg">{{ __('Canonical URL') }}</label>
                                        <input id="{{ $row['key'] }}-canonical" name="canonical_url" type="url" maxlength="500" dir="ltr" value="{{ $value('canonical_url', $row['canonical_url']) }}" placeholder="{{ __('Blank = this page') }}" class="{{ $field }}">
                                    </div>
                                </div>
                                <label class="flex items-center gap-2 text-sm text-fg">
                                    <input type="hidden" name="noindex" value="0">
                                    <input type="checkbox" name="noindex" value="1" @checked($value('noindex', $row['noindex'])) class="size-4 accent-brand">
                                    {{ __('Hide from search engines (noindex) and from the sitemap') }}
                                </label>
                                <div><x-ui.button size="sm">{{ __('Save SEO') }}</x-ui.button></div>
                            </form>

                            <div class="flex flex-col gap-2">
                                <p class="text-sm font-semibold text-fg">{{ __('Score checks') }}</p>
                                <ul class="flex flex-col gap-1.5 text-xs">
                                    @foreach ($row['score']['checks'] as $check)
                                        <li class="flex items-start gap-2 {{ $check['passed'] ? 'text-fg-success' : 'text-fg-secondary' }}">
                                            <span aria-hidden="true">{{ $check['passed'] ? '✓' : '○' }}</span>
                                            <span>{{ $check['label'] }} <span class="text-fg-tertiary">(+{{ $check['points'] }})</span><span class="sr-only">{{ $check['passed'] ? __('passed') : __('not met') }}</span></span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($row['edit_url'] && $row['type'] !== 'route')
                                    <p class="mt-2 text-xs text-fg-tertiary">{{ __('Title, slug and body are changed in the content editor.') }}</p>
                                @endif
                            </div>
                        </div>
                    </details>
                </li>
            @endforeach
        </ul>

        {{ $rows->links() }}
    @endif
</x-layouts.admin>
