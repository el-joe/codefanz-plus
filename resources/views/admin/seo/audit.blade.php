{{-- A12 · SEO Management & Audit: health score, issue categories, issues table, manual fix vs AI fix (approval). --}}
@php
    $metaFixTypes = [
        \App\Enums\SeoIssueType::MissingTitle, \App\Enums\SeoIssueType::DuplicateTitle, \App\Enums\SeoIssueType::TitleLength,
        \App\Enums\SeoIssueType::MissingDescription, \App\Enums\SeoIssueType::DuplicateDescription, \App\Enums\SeoIssueType::DescriptionLength,
        \App\Enums\SeoIssueType::CanonicalMismatch, \App\Enums\SeoIssueType::Noindex,
    ];
    $manualFix = function (\App\Models\SeoIssue $issue) use ($metaFixTypes, $targets): ?array {
        if ($issue->type === \App\Enums\SeoIssueType::BrokenInternalLink) {
            return [__('Create redirect'), route('admin.redirects.create', ['from' => $issue->details['link'] ?? ''])];
        }
        $type = $issue->seoable_type ?? ($issue->route_key ? 'route' : null);
        if ($type && in_array($issue->type, $metaFixTypes, true)) {
            return [__('Edit SEO'), route('admin.seo.meta', ['type' => $type, 'locale' => $issue->locale, 'q' => $issue->url])];
        }
        if ($issue->seoable && $targets->has($issue->seoable_type) && auth()->user()->can('update', $issue->seoable)) {
            return [__('Open editor'), $targets->editUrl($issue->seoable_type, $issue->seoable)];
        }
        if (in_array($issue->type, [\App\Enums\SeoIssueType::MissingAlt, \App\Enums\SeoIssueType::ImageWithoutAvif], true) && auth()->user()->can('media.manage')) {
            return [__('Open media'), route('admin.media.index')];
        }

        return null;
    };
    $detail = function (\App\Models\SeoIssue $issue): ?string {
        $details = $issue->details ?? [];

        return match (true) {
            isset($details['link']) => $details['link'].' → '.$details['status'],
            isset($details['status']) => (string) $details['status'],
            isset($details['length']) => __(':count characters', ['count' => $details['length']]),
            isset($details['count']) => __(':count found', ['count' => $details['count']]),
            isset($details['canonical']) => $details['canonical'],
            isset($details['alternate']) => $details['hreflang'].': '.$details['alternate'],
            isset($details['also_on']) => __('Also on :urls', ['urls' => implode(', ', $details['also_on'])]),
            isset($details['resources']) => implode(', ', $details['resources']),
            default => null,
        };
    };
    $score = $latest?->health_score;
    $scoreTone = $score === null ? 'text-fg' : ($score >= 80 ? 'text-fg-success' : ($score >= 50 ? 'text-fg-warning' : 'text-fg-danger'));
    $barTone = $score === null ? 'bg-muted' : ($score >= 80 ? 'bg-fg-success' : ($score >= 50 ? 'bg-fg-warning' : 'bg-fg-danger'));
@endphp

<x-layouts.admin :title="__('SEO health')" :crumb="__('Content & SEO')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        @include('admin.seo._tabs')
        <div class="flex flex-wrap items-center gap-3">
            @if ($latest)
                <p class="text-xs text-fg-tertiary">{{ __('Last crawl: :date · :count URLs', ['date' => $latest->finished_at?->diffForHumans(), 'count' => $latest->pages_crawled]) }}</p>
            @endif
            <form method="POST" action="{{ route('admin.seo.audits.store') }}">
                @csrf
                <x-ui.button :disabled="$inProgress !== null">{{ $inProgress ? __('Audit running…') : __('Run audit') }}</x-ui.button>
            </form>
        </div>
    </div>

    @if ($inProgress)
        <x-ui.alert tone="info" :title="__('Audit in progress')">{{ __('The crawler is checking the sitemap URLs in the background. Reload this page in a few minutes.') }}</x-ui.alert>
    @endif
    @if ($lastFailed)
        <x-ui.alert tone="danger" :title="__('The last audit failed')">{{ $lastFailed->error }}</x-ui.alert>
    @endif

    @if (! $latest)
        <x-ui.card>
            <x-ui.empty-state :title="__('No audit yet')" :description="__('Run an audit to crawl the public pages listed in the sitemap and find SEO issues. Nothing outside this site is crawled.')" />
        </x-ui.card>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[260px_1fr]">
            <x-ui.card class="flex flex-col items-center gap-2 text-center">
                <p class="text-sm font-semibold text-fg-secondary">{{ __('Site health') }}</p>
                <p class="text-6xl font-extrabold {{ $scoreTone }}" data-health-score>{{ $score ?? '—' }}</p>
                <p class="text-sm text-fg-tertiary">/100</p>
                <div class="h-2 w-full overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $score ?? 0 }}" aria-label="{{ __('Site health') }}">
                    <div class="h-full rounded-full {{ $barTone }}" style="width: {{ $score ?? 0 }}%"></div>
                </div>
                <p class="text-xs text-fg-tertiary">{{ __('Guides quality — doesn’t guarantee rankings.') }}</p>
            </x-ui.card>

            <ul class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Issue categories') }}">
                @foreach (\App\Enums\SeoIssueType::categories() as $category => $label)
                    @php($count = (int) ($categoryCounts[$category] ?? 0))
                    <li>
                        <a href="{{ route('admin.seo.audit', array_filter(['category' => ($filters['category'] ?? null) === $category ? null : $category, 'severity' => $filters['severity'] ?? null])) }}"
                           @if (($filters['category'] ?? null) === $category) aria-current="true" @endif
                           class="flex h-full flex-col gap-1 rounded-xl border bg-surface p-4 hover:bg-subtle {{ ($filters['category'] ?? null) === $category ? 'border-line-brand ring-2 ring-brand/20' : 'border-line' }}">
                            <span class="text-2xl font-bold {{ $count === 0 ? 'text-fg-success' : 'text-fg-warning' }}">{{ $count }}</span>
                            <span class="text-sm text-fg-secondary">{{ $label }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="flex flex-wrap items-center gap-2" aria-label="{{ __('Filter by severity') }}">
            <span class="text-sm text-fg-secondary">{{ __('Severity') }}:</span>
            @foreach ([null => __('All'), ...collect(\App\Enums\SeoSeverity::cases())->mapWithKeys(fn ($severity) => [$severity->value => $severity->label()])->all()] as $value => $label)
                @php($selected = ($filters['severity'] ?? '') === (string) $value)
                <a href="{{ route('admin.seo.audit', array_filter(['category' => $filters['category'] ?? null, 'severity' => $value ?: null])) }}" @if ($selected) aria-current="true" @endif
                   class="rounded-full border px-3 py-1 text-sm font-semibold {{ $selected ? 'border-line-brand bg-brand-subtle text-fg-brand' : 'border-line text-fg-secondary hover:bg-subtle' }}">{{ $label }}</a>
            @endforeach
        </div>

        <x-ui.card padding="p-0" class="overflow-x-auto">
            @if ($issues->isEmpty())
                <x-ui.empty-state :title="__('No issues found')" :description="__('Nothing to fix for this filter in the latest audit.')" />
            @else
                <table class="w-full text-sm">
                    <thead class="bg-subtle text-xs text-fg-tertiary">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Issue') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('URL') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Severity') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Locale') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('Detected') }}</th>
                            <th scope="col" class="px-4 py-3 text-start font-semibold"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($issues as $issue)
                            @php($fix = $manualFix($issue))
                            @php($aiReason = $issue->type->isAiFixable() ? $aiFix->unavailableReason($issue) : null)
                            <tr class="border-t border-line-subtle align-top" data-issue="{{ $issue->type->value }}">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-fg">{{ $issue->type->label() }}</p>
                                    @if ($text = $detail($issue))
                                        <p class="mt-0.5 max-w-sm break-all text-xs text-fg-tertiary" dir="auto">{{ \Illuminate\Support\Str::limit($text, 160) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><a href="{{ $issue->url }}" target="_blank" rel="noopener" class="break-all font-mono text-xs text-fg-brand hover:underline" dir="ltr">{{ rawurldecode((string) parse_url($issue->url, PHP_URL_PATH)) }}</a></td>
                                <td class="px-4 py-3"><x-ui.badge :tone="$issue->severity->tone()">{{ $issue->severity->label() }}</x-ui.badge></td>
                                <td class="px-4 py-3 font-mono text-xs text-fg-secondary">{{ strtoupper((string) $issue->locale) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-fg-secondary">{{ $issue->created_at->translatedFormat('M j') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col items-start gap-2">
                                        @if ($fix)
                                            <x-ui.button :href="$fix[1]" variant="secondary" size="sm">{{ $fix[0] }}</x-ui.button>
                                        @endif
                                        @if ($issue->type->isAiFixable())
                                            @if ($aiReason === null && auth()->user()->can('ai.use'))
                                                <form method="POST" action="{{ route('admin.seo.issues.ai-fix', $issue) }}">
                                                    @csrf
                                                    <x-ui.button variant="ghost" size="sm" data-ai-fix>{{ __('AI fix → approval') }}</x-ui.button>
                                                </form>
                                            @else
                                                <x-ui.button variant="ghost" size="sm" type="button" disabled aria-describedby="ai-note-{{ $issue->id }}" data-ai-fix-disabled>{{ __('AI fix → approval') }}</x-ui.button>
                                                <p id="ai-note-{{ $issue->id }}" class="max-w-48 text-xs text-fg-tertiary">{{ $aiReason ?? __('Requires the AI permission.') }}</p>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>

        {{ $issues->links() }}
    @endif

    <x-ui.alert tone="info" :title="__('How fixes are applied')">
        {{ __('Manual fixes by authorized editors apply immediately and are audited. AI-suggested fixes create change requests in the Approval Queue and change nothing until a reviewer approves them.') }}
    </x-ui.alert>
</x-layouts.admin>
