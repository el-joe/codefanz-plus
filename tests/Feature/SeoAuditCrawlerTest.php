<?php

use App\Enums\SeoAuditStatus;
use App\Enums\SeoIssueType;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Models\Service;
use App\Services\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/**
 * A healthy page: unique title and description, one H1, self canonical, AVIF picture, alt text.
 */
function healthyPage(string $url, array $hreflang = []): string
{
    $id = substr(md5($url), 0, 10);
    $links = collect($hreflang)->map(fn (string $href, string $lang) => '<link rel="alternate" hreflang="'.$lang.'" href="'.$href.'">')->implode('');

    return '<!DOCTYPE html><html><head><title>Software services page '.$id.' · CodeFanz agency</title>'
        .'<meta name="description" content="A complete and accurate description of page '.$id.' for search results, long enough to pass the length check.">'
        .'<link rel="canonical" href="'.$url.'">'.$links.'</head><body><h1>Page '.$id.'</h1>'
        .'<picture><source type="image/avif" srcset="/storage/a.avif"><img src="/storage/a.webp" width="1200" alt="Cover"></picture>'
        .'<a href="/en/services">Services</a></body></html>';
}

test('the crawler detects every issue type on a faked internal site', function () {
    URL::forceScheme('https');
    app(Settings::class)->set('seo', 'block_indexing', 'allow');

    Service::factory()->withTranslation('en', ['title' => 'Broken', 'slug' => 'broken'])->withTranslation('ar', ['title' => 'معطل', 'slug' => 'معطل'])->create();
    Service::factory()->withTranslation('en', ['title' => 'Twin A', 'slug' => 'twin-a'])->create();
    Service::factory()->withTranslation('en', ['title' => 'Twin B', 'slug' => 'twin-b'])->create();
    Service::factory()->withTranslation('en', ['title' => 'Down', 'slug' => 'down'])->create();

    $broken = route('services.show', ['locale' => 'en', 'slug' => 'broken']);
    $brokenAr = route('services.show', ['locale' => 'ar', 'slug' => 'معطل']);
    $twinA = route('services.show', ['locale' => 'en', 'slug' => 'twin-a']);
    $twinB = route('services.show', ['locale' => 'en', 'slug' => 'twin-b']);
    $down = route('services.show', ['locale' => 'en', 'slug' => 'down']);
    $twin = '<!DOCTYPE html><html><head><title>Same title for both twin service pages here</title><meta name="description" content="Exactly the same description on two different pages, which the crawler should flag as duplicate.">';

    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($broken, $brokenAr, $twinA, $twinB, $down, $twin) {
        $url = $request->url();

        return match (true) {
            $url === $broken => Http::response('<!DOCTYPE html><html><head><title></title><meta name="robots" content="noindex">'
                .'<link rel="canonical" href="https://localhost/en/somewhere-else"><link rel="alternate" hreflang="ar" href="'.$brokenAr.'"></head>'
                .'<body><h1>One</h1><h1>Two</h1><img src="/storage/photo.jpg" width="1600"><img src="/storage/x.png" alt="">'
                .'<script src="http://cdn.example.com/lib.js"></script><a href="/en/missing-page">Old link</a><a href="mailto:a@b.c">Mail</a></body></html>'),
            $url === $brokenAr => Http::response(healthyPage($brokenAr)),
            $url === $twinA => Http::response($twin.'<link rel="canonical" href="'.$twinA.'"></head><body><h1>A</h1></body></html>'),
            $url === $twinB => Http::response($twin.'<link rel="canonical" href="'.$twinB.'"></head><body><h1>B</h1></body></html>'),
            $url === $down => Http::response('Server error', 500),
            str_ends_with($url, '/en/missing-page') => Http::response('Not found', 404),
            str_contains($url, '/en/contact') => Http::response('<!DOCTYPE html><html><head><title>Short</title><meta name="description" content="Too short."><link rel="canonical" href="'.$url.'"></head><body><p>No heading</p></body></html>'),
            default => Http::response(healthyPage($url)),
        };
    });

    $audit = SeoAudit::create(['status' => SeoAuditStatus::Queued]);
    Artisan::call('seo:audit', ['--sync' => true]);
    $audit = SeoAudit::query()->latest('id')->first();

    expect($audit->status)->toBe(SeoAuditStatus::Completed)
        ->and($audit->pages_crawled)->toBeGreaterThan(30)
        ->and($audit->health_score)->toBeLessThan(100)->toBeGreaterThan(50);

    $found = SeoIssue::query()->where('seo_audit_id', $audit->id)->get();
    $typesFor = fn (string $url) => $found->where('url', $url)->pluck('type')->map->value->unique()->values()->all();

    expect($typesFor($broken))->toContain(
        SeoIssueType::MissingTitle->value, SeoIssueType::MissingDescription->value, SeoIssueType::MultipleH1->value,
        SeoIssueType::MissingAlt->value, SeoIssueType::ImageWithoutAvif->value, SeoIssueType::CanonicalMismatch->value,
        SeoIssueType::Noindex->value, SeoIssueType::MixedContent->value, SeoIssueType::HreflangReciprocity->value,
        SeoIssueType::BrokenInternalLink->value,
    )
        ->and($typesFor($twinA))->toContain(SeoIssueType::DuplicateTitle->value, SeoIssueType::DuplicateDescription->value)
        ->and($typesFor($down))->toBe([SeoIssueType::HttpStatus->value])
        ->and($typesFor(route('contact', ['locale' => 'en'])))->toContain(SeoIssueType::TitleLength->value, SeoIssueType::DescriptionLength->value, SeoIssueType::MissingH1->value)
        ->and($typesFor($brokenAr))->toBe([]);

    $brokenLink = $found->firstWhere('type', SeoIssueType::BrokenInternalLink);
    expect($brokenLink->details['link'])->toEndWith('/en/missing-page')
        ->and($brokenLink->seoable_type)->toBe('service')
        ->and($brokenLink->locale)->toBe('en');

    // Only internal URLs were requested: no external fetches without the outbound check enabled.
    Http::assertNotSent(fn (Request $request): bool => ! str_starts_with($request->url(), 'https://localhost'));
    expect(SeoAudit::query()->where('status', SeoAuditStatus::Queued)->whereKey($audit->id - 1)->exists())->toBeTrue();
});

test('outbound links get a HEAD check only when enabled, and blocked indexing is reported once', function () {
    config(['codefanz.seo.audit.check_external_links' => true]);
    Service::factory()->withTranslation('en', ['title' => 'Links', 'slug' => 'links'])->create();
    $page = route('services.show', ['locale' => 'en', 'slug' => 'links']);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($page) {
        return match (true) {
            $request->url() === $page => Http::response(str_replace('</body>', '<a href="https://gone.example/page">Gone</a><a href="ftp://files.example/x">FTP</a></body>', healthyPage($page))),
            str_starts_with($request->url(), 'https://gone.example') => Http::response('', 404),
            default => Http::response(healthyPage($request->url())),
        };
    });

    Artisan::call('seo:audit', ['--sync' => true]);
    $audit = SeoAudit::query()->latest('id')->first();

    expect(SeoIssue::query()->where('type', SeoIssueType::BrokenExternalLink)->sole()->details['link'])->toBe('https://gone.example/page')
        ->and(SeoIssue::query()->where('type', SeoIssueType::IndexingBlocked)->count())->toBe(1)
        ->and(SeoIssue::query()->where('type', SeoIssueType::Noindex)->count())->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD' && $request->url() === 'https://gone.example/page');
    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'ftp://'));
    expect($audit->status)->toBe(SeoAuditStatus::Completed);
});

test('an unreachable site fails the audit with an honest message', function () {
    Http::fake(fn () => Http::failedConnection('refused'));

    Artisan::call('seo:audit', ['--sync' => true]);

    $audit = SeoAudit::query()->sole();
    expect($audit->status)->toBe(SeoAuditStatus::Failed)
        ->and($audit->error)->toContain('could not be reached')
        ->and(SeoIssue::query()->count())->toBe(0);
});
