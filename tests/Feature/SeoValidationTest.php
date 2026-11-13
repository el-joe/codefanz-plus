<?php

use App\Models\Service;
use App\Services\Settings;

/**
 * SEO validation across every public route type in English and Arabic (M11.2): unique titles and descriptions,
 * self canonicals, reciprocal hreflang with x-default, OpenGraph, robots, parseable JSON-LD and clean sitemaps.
 */

/**
 * @return array{title: string, description: ?string, canonical: ?string, robots: ?string, alternates: array<string, string>, og: array<string, string>, jsonLd: list<string>}
 */
function seoHead(string $html): array
{
    $xpath = htmlXPath($html);
    $attribute = fn (string $query, string $name): ?string => $xpath->query($query)->item(0)?->getAttribute($name);

    $alternates = [];
    foreach ($xpath->query('//link[@rel="alternate"][@hreflang]') as $link) {
        $alternates[$link->getAttribute('hreflang')] = $link->getAttribute('href');
    }

    $og = [];
    foreach ($xpath->query('//meta[starts-with(@property, "og:")]') as $meta) {
        $og[$meta->getAttribute('property')] = $meta->getAttribute('content');
    }

    return [
        'title' => trim($xpath->query('//title')->item(0)?->textContent ?? ''),
        'description' => $attribute('//meta[@name="description"]', 'content'),
        'canonical' => $attribute('//link[@rel="canonical"]', 'href'),
        'robots' => $attribute('//meta[@name="robots"]', 'content'),
        'alternates' => $alternates,
        'og' => $og,
        'jsonLd' => collect(iterator_to_array($xpath->query('//script[@type="application/ld+json"]')))->map(fn (DOMElement $script): string => $script->textContent)->all(),
    ];
}

test('every public route type has unique titles and descriptions, self canonicals, reciprocal hreflang and OpenGraph', function () {
    app(Settings::class)->set('seo', 'block_indexing', 'allow');
    $heads = [];

    foreach (publicSiteRoutes() as $route => $paths) {
        foreach ($paths as $locale => $path) {
            $head = seoHead($this->get($path)->assertOk()->getContent());
            $url = url($path);
            $label = "{$route} ({$locale})";

            expect($head['title'])->not->toBe('', $label)
                ->and($head['description'])->not->toBeEmpty("{$label} has no meta description")
                ->and(urldecode((string) $head['canonical']))->toBe(urldecode($url), "{$label} canonical")
                ->and($head['robots'] ?? '')->not->toContain('noindex', "{$label} must be indexable")
                ->and($head['og']['og:title'] ?? null)->not->toBeEmpty($label)
                ->and($head['og']['og:description'] ?? null)->not->toBeEmpty($label)
                ->and(urldecode($head['og']['og:url'] ?? ''))->toBe(urldecode($url), "{$label} og:url")
                ->and($head['og']['og:locale'] ?? null)->toBe($locale === 'en' ? 'en_US' : 'ar_AR')
                ->and(array_keys($head['alternates']))->toEqualCanonicalizing(['en', 'ar', 'x-default'], "{$label} hreflang set")
                ->and(urldecode($head['alternates'][$locale]))->toBe(urldecode($url), "{$label} lists itself as its own alternate")
                ->and($head['alternates']['x-default'])->toBe($head['alternates']['en']);

            $heads[$route][$locale] = $head;
        }

        // Reciprocity: the English page points to the Arabic page and the Arabic page points back.
        expect(urldecode($heads[$route]['en']['alternates']['ar']))->toBe(urldecode(url($paths['ar'])), "{$route} en → ar")
            ->and(urldecode($heads[$route]['ar']['alternates']['en']))->toBe(urldecode(url($paths['en'])), "{$route} ar → en");
    }

    foreach (['en', 'ar'] as $locale) {
        $titles = collect($heads)->map(fn (array $pair): string => $pair[$locale]['title']);
        $descriptions = collect($heads)->map(fn (array $pair): string => (string) $pair[$locale]['description']);

        expect($titles->duplicates()->all())->toBe([], "duplicate {$locale} titles")
            ->and($descriptions->duplicates()->all())->toBe([], "duplicate {$locale} descriptions");
    }
});

test('every JSON-LD block on every public route type parses and declares a schema.org type', function () {
    foreach (publicSiteRoutes() as $route => $paths) {
        foreach ($paths as $locale => $path) {
            $blocks = seoHead($this->get($path)->assertOk()->getContent())['jsonLd'];

            expect($blocks)->not->toBeEmpty("{$route} ({$locale}) has no structured data");

            foreach ($blocks as $json) {
                $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                $nodes = isset($data['@graph']) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);

                expect($data['@context'] ?? $nodes[0]['@context'] ?? null)->toBe('https://schema.org', "{$route} ({$locale}) @context");

                foreach ($nodes as $node) {
                    expect($node['@type'] ?? null)->not->toBeEmpty("{$route} ({$locale}) node without @type");
                }
            }
        }
    }
});

test('the sitemap index and every child are well-formed and list only reachable, indexable URLs', function () {
    app(Settings::class)->set('seo', 'block_indexing', 'allow');
    publicSiteRoutes();

    $index = simplexml_load_string($this->get(route('sitemap.index'))->assertOk()->getContent());
    expect($index)->not->toBeFalse()->and($index->getName())->toBe('sitemapindex');

    $checked = 0;
    foreach ($index->sitemap as $sitemap) {
        $child = $this->get((string) parse_url((string) $sitemap->loc, PHP_URL_PATH))->assertOk();
        $xml = simplexml_load_string($child->getContent());

        expect($xml)->not->toBeFalse((string) $sitemap->loc)->and($xml->getName())->toBe('urlset');

        foreach ($xml->url as $entry) {
            $location = (string) $entry->loc;
            $response = $this->get(parse_url($location, PHP_URL_PATH));

            expect($response->getStatusCode())->toBe(200, "{$location} is listed in a sitemap but answers {$response->getStatusCode()}")
                ->and(seoHead($response->getContent())['robots'] ?? '')->not->toContain('noindex', "{$location} is listed but noindex")
                ->and((string) $response->headers->get('X-Robots-Tag'))->not->toContain('noindex');
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(30);
});

test('robots.txt follows the environment', function (string $environment, ?string $setting, bool $indexable) {
    $this->app->detectEnvironment(fn () => $environment);

    if ($setting) {
        app(Settings::class)->set('seo', 'block_indexing', $setting);
    }

    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect(str_contains($robots, "Disallow: /\n"))->toBe(! $indexable)
        ->and(str_contains($robots, 'Sitemap: '.route('sitemap.index')))->toBe($indexable);
})->with([
    'local' => ['local', null, false],
    'staging' => ['staging', null, false],
    'production' => ['production', null, true],
    'staging opened from settings' => ['staging', 'allow', true],
    'production blocked from settings' => ['production', 'block', false],
]);

test('unknown pages answer 404 in both languages and the language root redirects', function () {
    publicSiteRoutes();

    foreach (['/en/no-such-page', '/ar/صفحة-غير-موجودة', '/en/services/no-such-service', '/ar/blog/لا-يوجد', '/en/portfolio/missing', '/fr/about'] as $path) {
        $this->get($path)->assertNotFound();
    }
});

test('the bare domain negotiates the language with a temporary redirect', function (string $acceptLanguage, string $locale) {
    // "/" depends on the session or Accept-Language, so it must not be a cacheable 301.
    $this->get('/', ['Accept-Language' => $acceptLanguage])->assertStatus(302)->assertRedirect(route('home', ['locale' => $locale]));
})->with([
    'arabic browser' => ['ar-EG,ar;q=0.9', 'ar'],
    'english browser' => ['en-US,en;q=0.9', 'en'],
]);

test('changed slugs answer a single 301 to the new URL', function () {
    publicSiteRoutes();
    $service = Service::query()->firstOrFail();
    $service->translations()->where('locale', 'en')->first()->update(['slug' => 'web-apps']);

    $this->get('/en/services/web-development')
        ->assertStatus(301)
        ->assertRedirect(route('services.show', ['locale' => 'en', 'slug' => 'web-apps']));
    $this->get(route('services.show', ['locale' => 'en', 'slug' => 'web-apps']))->assertOk();
});
