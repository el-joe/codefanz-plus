<?php

namespace App\Services\Seo;

use App\Enums\SeoAuditStatus;
use App\Enums\SeoIssueType;
use App\Enums\SeoSeverity;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Crawls the site's own public URLs (the sitemap list, never discovered external pages) and records SEO issues.
 * Internal links not in the sitemap are checked for errors; outbound links get an optional HEAD request with a
 * strict timeout (http/https only). The health score is the average page score (100 minus severity penalties).
 */
class SeoCrawler
{
    /**
     * @var array<string, array{loc: string, locale: string, type: string, id: int|null, route_key: string|null}>
     */
    private array $targets = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $issues = [];

    public function __construct(
        private SitemapBuilder $sitemaps,
        private HtmlInspector $inspector,
        private IndexingPolicy $indexing,
        private SeoTargets $seoTargets,
        private OutboundUrlGuard $guard,
    ) {}

    public function run(SeoAudit $audit): SeoAudit
    {
        $audit->forceFill(['status' => SeoAuditStatus::Running, 'started_at' => now()])->save();

        try {
            $this->targets = collect($this->sitemaps->allUrls())
                ->take((int) config('codefanz.seo.audit.max_pages'))
                ->keyBy(fn (array $url): string => $this->normalize($url['loc']))
                ->all();
            $this->issues = [];

            $pages = $this->crawl();

            if ($pages !== [] && collect($pages)->every(fn (array $page): bool => $page['status'] === 0)) {
                return $this->fail($audit, __('The site could not be reached at :url. Check APP_URL.', ['url' => config('app.url')]));
            }

            $this->checkDuplicates($pages);
            $this->checkHreflang($pages);
            $this->checkLinks($pages);

            if ($this->indexing->blocksIndexing() && $pages !== []) {
                $this->add(SeoIssueType::IndexingBlocked, array_key_first($pages), ['setting' => 'seo.block_indexing']);
            }

            $health = $this->health(array_keys($pages));

            foreach (array_chunk($this->issues, 200) as $chunk) {
                SeoIssue::query()->insert(array_map(fn (array $issue): array => [...$issue, 'seo_audit_id' => $audit->id, 'details' => json_encode($issue['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'created_at' => now(), 'updated_at' => now()], $chunk));
            }

            $audit->forceFill([
                'status' => SeoAuditStatus::Completed,
                'pages_crawled' => count($pages),
                'health_score' => $health,
                'summary' => [
                    'by_type' => collect($this->issues)->countBy('type')->all(),
                    'by_severity' => collect($this->issues)->countBy('severity')->all(),
                ],
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            return $this->fail($audit, __('The audit stopped unexpectedly.'));
        }

        return $audit;
    }

    /**
     * @return array<string, array<string, mixed>> normalized url => page facts
     */
    private function crawl(): array
    {
        $pages = [];

        foreach ($this->targets as $key => $target) {
            $response = $this->fetch($target['loc']);
            $status = $response?->status() ?? 0;
            $page = ['url' => $target['loc'], 'status' => $status, 'facts' => null];

            if ($status !== 200) {
                $this->add(SeoIssueType::HttpStatus, $key, ['status' => $status ?: __('unreachable')]);
                $pages[$key] = $page;

                continue;
            }

            $facts = $this->inspector->inspect($response->body(), $target['loc']);
            $page['facts'] = $facts;
            $pages[$key] = $page;
            $this->checkPage($key, $target['loc'], $facts, (string) $response->header('X-Robots-Tag'));
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function checkPage(string $key, string $url, array $facts, string $robotsHeader): void
    {
        $title = (string) $facts['title'];
        $description = (string) $facts['description'];

        if ($title === '') {
            $this->add(SeoIssueType::MissingTitle, $key);
        } elseif (mb_strlen($title) < SeoScore::TITLE_MIN || mb_strlen($title) > SeoScore::TITLE_MAX) {
            $this->add(SeoIssueType::TitleLength, $key, ['length' => mb_strlen($title), 'title' => $title]);
        }

        if ($description === '') {
            $this->add(SeoIssueType::MissingDescription, $key);
        } elseif (mb_strlen($description) < SeoScore::DESCRIPTION_MIN || mb_strlen($description) > SeoScore::DESCRIPTION_MAX) {
            $this->add(SeoIssueType::DescriptionLength, $key, ['length' => mb_strlen($description)]);
        }

        if ($facts['h1'] === 0) {
            $this->add(SeoIssueType::MissingH1, $key);
        } elseif ($facts['h1'] > 1) {
            $this->add(SeoIssueType::MultipleH1, $key, ['count' => $facts['h1']]);
        }

        $missingAlt = array_values(array_filter($facts['images'], fn (array $image): bool => ! $image['has_alt']));

        if ($missingAlt !== []) {
            $this->add(SeoIssueType::MissingAlt, $key, ['count' => count($missingAlt), 'images' => array_slice(array_column($missingAlt, 'src'), 0, 5)]);
        }

        $minWidth = (int) config('codefanz.seo.audit.large_image_width');
        $withoutAvif = array_values(array_filter($facts['images'], fn (array $image): bool => ! $image['avif']
            && preg_match('/\.(jpe?g|png|webp)(\?.*)?$/i', $image['src']) === 1
            && ($image['width'] === null || $image['width'] >= $minWidth)));

        if ($withoutAvif !== []) {
            $this->add(SeoIssueType::ImageWithoutAvif, $key, ['count' => count($withoutAvif), 'images' => array_slice(array_column($withoutAvif, 'src'), 0, 5)]);
        }

        if ($facts['canonical'] === null || $this->normalize($facts['canonical']) !== $key) {
            $this->add(SeoIssueType::CanonicalMismatch, $key, ['canonical' => $facts['canonical'] ?? __('missing')]);
        }

        $robots = strtolower((string) $facts['robots']).' '.($this->indexing->blocksIndexing() ? '' : strtolower($robotsHeader));

        if (str_contains($robots, 'noindex')) {
            $this->add(SeoIssueType::Noindex, $key, ['robots' => trim($robots)]);
        }

        if (str_starts_with(strtolower($url), 'https://') && $facts['insecure'] !== []) {
            $this->add(SeoIssueType::MixedContent, $key, ['resources' => array_slice($facts['insecure'], 0, 5)]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     */
    private function checkDuplicates(array $pages): void
    {
        foreach (['title' => SeoIssueType::DuplicateTitle, 'description' => SeoIssueType::DuplicateDescription] as $field => $type) {
            collect($pages)
                ->filter(fn (array $page): bool => filled($page['facts'][$field] ?? null))
                ->groupBy(fn (array $page): string => mb_strtolower($page['facts'][$field]), preserveKeys: true)
                ->filter(fn ($group): bool => $group->count() > 1)
                ->each(function ($group) use ($type): void {
                    foreach ($group->keys() as $key) {
                        $this->add($type, $key, ['also_on' => $group->keys()->reject(fn (string $other): bool => $other === $key)->map(fn (string $other): string => $this->targets[$other]['loc'])->take(5)->values()->all()]);
                    }
                });
        }
    }

    /**
     * Every hreflang alternate that was crawled must point back to this page.
     *
     * @param  array<string, array<string, mixed>>  $pages
     */
    private function checkHreflang(array $pages): void
    {
        foreach ($pages as $key => $page) {
            foreach ($page['facts']['hreflang'] ?? [] as $language => $alternate) {
                $alternateKey = $this->normalize($alternate);

                if ($language === 'x-default' || $alternateKey === $key || ! isset($pages[$alternateKey]['facts'])) {
                    continue;
                }

                $backLinks = array_map(fn (string $url): string => $this->normalize($url), $pages[$alternateKey]['facts']['hreflang']);

                if (! in_array($key, $backLinks, true)) {
                    $this->add(SeoIssueType::HreflangReciprocity, $key, ['alternate' => $alternate, 'hreflang' => $language]);
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $pages
     */
    private function checkLinks(array $pages): void
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $internal = [];
        $external = [];

        foreach ($pages as $key => $page) {
            foreach ($page['facts']['links'] ?? [] as $link) {
                $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

                if (! in_array($scheme, ['http', 'https'], true)) {
                    continue;
                }

                $normalized = $this->normalize($link);

                if (strcasecmp((string) parse_url($link, PHP_URL_HOST), $host) === 0) {
                    if (! isset($pages[$normalized])) {
                        $internal[$normalized]['url'] = $link;
                        $internal[$normalized]['sources'][] = $key;
                    }
                } else {
                    $external[$link][] = $key;
                }
            }
        }

        foreach (array_slice($internal, 0, (int) config('codefanz.seo.audit.max_internal_links'), true) as $link) {
            $status = $this->fetch($link['url'])?->status() ?? 0;

            if ($status === 0 || $status >= 400) {
                foreach (array_unique($link['sources']) as $source) {
                    $this->add(SeoIssueType::BrokenInternalLink, $source, ['link' => $link['url'], 'status' => $status ?: __('unreachable')]);
                }
            }
        }

        if (! config('codefanz.seo.audit.check_external_links')) {
            return;
        }

        foreach (array_slice($external, 0, (int) config('codefanz.seo.audit.max_external_links'), true) as $link => $sources) {
            // SSRF: links in content may point anywhere; only public hosts are requested, every redirect re-checked.
            try {
                $status = $this->guard->isAllowed($link)
                    ? Http::timeout(5)->connectTimeout(3)->withOptions($this->guard->requestOptions($link, 3))->head($link)->status()
                    : 0;
            } catch (Throwable) {
                $status = 0;
            }

            if ($status === 0 || $status >= 400 && ! in_array($status, [403, 405, 429], true)) {
                foreach (array_unique($sources) as $source) {
                    $this->add(SeoIssueType::BrokenExternalLink, $source, ['link' => $link, 'status' => $status ?: __('unreachable')]);
                }
            }
        }
    }

    private function fetch(string $url): ?Response
    {
        try {
            return Http::timeout(15)->connectTimeout(5)
                ->withHeaders(['User-Agent' => 'CodeFanzSeoAudit/1.0', 'Accept' => 'text/html'])
                ->withoutRedirecting()
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function add(SeoIssueType $type, string $key, array $details = []): void
    {
        $target = $this->targets[$key] ?? null;
        $isEntity = $target !== null && $target['type'] !== 'route' && $this->seoTargets->has($target['type']);

        $this->issues[] = [
            'type' => $type->value,
            'category' => $type->category(),
            'severity' => $type->severity()->value,
            'url' => mb_substr($target['loc'] ?? $key, 0, 1000),
            'locale' => $target['locale'] ?? null,
            'seoable_type' => $isEntity ? $target['type'] : null,
            'seoable_id' => $isEntity ? $target['id'] : null,
            'route_key' => $target['route_key'] ?? null,
            'details' => $details,
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private function health(array $keys): ?int
    {
        if ($keys === []) {
            return null;
        }

        $penalties = collect($this->issues)->groupBy('url')->map(fn ($issues): int => $issues->sum(fn (array $issue): int => SeoSeverity::from($issue['severity'])->penalty()));

        return (int) round(collect($keys)->avg(fn (string $key): int => max(0, 100 - (int) $penalties->get($this->targets[$key]['loc'] ?? $key, 0))));
    }

    private function fail(SeoAudit $audit, string $message): SeoAudit
    {
        $audit->forceFill(['status' => SeoAuditStatus::Failed, 'error' => mb_substr($message, 0, 500), 'finished_at' => now()])->save();

        return $audit;
    }

    /**
     * Comparable form of a URL: decoded path without trailing slash, no fragment, lowercase scheme/host.
     */
    private function normalize(string $url): string
    {
        $parts = parse_url($url);
        $path = rawurldecode($parts['path'] ?? '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        return strtolower(($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? '')).(isset($parts['port']) ? ':'.$parts['port'] : '').$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
