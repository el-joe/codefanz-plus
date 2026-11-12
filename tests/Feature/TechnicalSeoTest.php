<?php

use App\Enums\ContentStatus;
use App\Enums\ProjectVisibility;
use App\Models\Career;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Services\Seo\StructuredData;
use App\Services\Settings;

function bilingualService(string $en = 'web-development', string $ar = 'تطوير-الويب', array $state = []): Service
{
    return Service::factory()
        ->withTranslation('en', ['title' => 'Web Development', 'slug' => $en])
        ->withTranslation('ar', ['title' => 'تطوير الويب', 'slug' => $ar])
        ->create($state);
}

function sitemapXml(string $body): SimpleXMLElement
{
    $xml = simplexml_load_string($body);
    expect($xml)->not->toBeFalse();

    return $xml;
}

test('the sitemap index lists one child per type and language with content', function () {
    bilingualService();
    Post::factory()->withTranslation('en', ['title' => 'Queues in production'])->create();

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = sitemapXml($response->getContent());
    $locations = [];
    foreach ($xml->sitemap as $sitemap) {
        $locations[] = (string) $sitemap->loc;
    }

    expect($xml->getName())->toBe('sitemapindex')
        ->and($locations)->toContain(route('sitemap.child', ['name' => 'static-en']), route('sitemap.child', ['name' => 'services-en']), route('sitemap.child', ['name' => 'services-ar']), route('sitemap.child', ['name' => 'posts-en']))
        ->and($locations)->not->toContain(route('sitemap.child', ['name' => 'posts-ar']), route('sitemap.child', ['name' => 'products-en']));

    $this->get('/sitemaps/unknown-en.xml')->assertNotFound();
    $this->get('/sitemaps/services-fr.xml')->assertNotFound();
});

test('child sitemaps are valid XML with lastmod and reciprocal hreflang alternates', function () {
    $service = bilingualService();

    $xml = sitemapXml($this->get(route('sitemap.child', ['name' => 'services-ar']))->assertOk()->getContent());
    $url = $xml->url[0];
    $links = [];
    foreach ($url->children('http://www.w3.org/1999/xhtml')->link as $link) {
        $links[(string) $link->attributes()->hreflang] = (string) $link->attributes()->href;
    }

    expect($xml->getName())->toBe('urlset')
        ->and(count($xml->url))->toBe(1)
        ->and((string) $url->loc)->toBe(route('services.show', ['locale' => 'ar', 'slug' => 'تطوير-الويب']))
        ->and((string) $url->lastmod)->toBe($service->updated_at->toAtomString())
        ->and($links)->toEqualCanonicalizing([
            'en' => route('services.show', ['locale' => 'en', 'slug' => 'web-development']),
            'ar' => route('services.show', ['locale' => 'ar', 'slug' => 'تطوير-الويب']),
            'x-default' => route('services.show', ['locale' => 'en', 'slug' => 'web-development']),
        ]);

    $static = sitemapXml($this->get(route('sitemap.child', ['name' => 'static-en']))->getContent());
    $locs = [];
    foreach ($static->url as $entry) {
        $locs[] = (string) $entry->loc;
    }
    expect($locs)->toContain(route('home', ['locale' => 'en']), route('blog.index', ['locale' => 'en']))
        ->and($locs)->not->toContain(route('search', ['locale' => 'en']));
});

test('drafts, unlisted, closed, noindex and canonicalised items are left out of the sitemaps', function () {
    bilingualService('live-service', 'خدمة');
    bilingualService('draft-service', 'مسودة', ['status' => ContentStatus::Draft]);
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Hidden', 'slug' => 'unlisted-project'])->create(['visibility' => ProjectVisibility::Unlisted]);
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Public', 'slug' => 'public-project'])->create();
    Career::factory()->withTranslation('en', ['title' => 'Closed', 'slug' => 'closed-role'])->create(['closes_at' => now()->subWeek()]);
    Post::factory()->withTranslation('en', ['title' => 'Syndicated', 'slug' => 'syndicated', 'canonical_url' => 'https://example.com/original'])->create();
    Post::factory()->withTranslation('en', ['title' => 'Future', 'slug' => 'future-post'])->create(['published_at' => now()->addDay()]);
    Post::factory()->draft()->withTranslation('en', ['title' => 'Draft', 'slug' => 'draft-post'])->create();
    $noindex = Product::factory()->withTranslation('en', ['name' => 'Hidden product', 'slug' => 'hidden-product'])->create();
    SeoMeta::create(['seoable_type' => 'product', 'seoable_id' => $noindex->id, 'locale' => 'en', 'noindex' => true]);
    SeoMeta::create(['route_key' => 'testimonials.index', 'locale' => 'en', 'noindex' => true]);

    $all = collect(['static', 'services', 'portfolio', 'products', 'careers', 'posts'])
        ->map(fn (string $type) => $this->get(route('sitemap.child', ['name' => $type.'-en']))->getContent())
        ->implode('');

    expect($all)->toContain('live-service')->toContain('public-project')
        ->not->toContain('draft-service')->not->toContain('unlisted-project')->not->toContain('closed-role')
        ->not->toContain('syndicated')->not->toContain('future-post')->not->toContain('draft-post')
        ->not->toContain('hidden-product')->not->toContain('/en/testimonials<');
});

test('sitemaps are cached and rebuilt when content is published or unpublished', function () {
    $service = bilingualService();
    $url = route('sitemap.child', ['name' => 'services-en']);

    $this->get($url)->assertSee('web-development');

    $service->forceFill(['status' => ContentStatus::Draft])->save();
    $this->get($url)->assertDontSee('web-development');

    $service->forceFill(['status' => ContentStatus::Published])->save();
    $service->translations()->where('locale', 'en')->first()->update(['slug' => 'web-apps']);
    $this->get($url)->assertSee('web-apps')->assertDontSee('web-development');
});

test('robots.txt blocks everything outside production by default and sends a noindex header everywhere', function () {
    $this->get('/robots.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText("User-agent: *\nDisallow: /", false)
        ->assertDontSee('Sitemap:');

    $this->get(route('home', ['locale' => 'en']))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('robots.txt in production disallows private areas and search and announces the sitemap', function () {
    $this->app->detectEnvironment(fn () => 'production');

    $response = $this->get('/robots.txt')->assertOk();

    $response->assertSee('Disallow: /admin')->assertSee('Disallow: /portal')->assertSee('Disallow: /pay')
        ->assertSee('Disallow: /webhooks')->assertSee('Disallow: /en/search')->assertSee('Disallow: /ar/search')
        ->assertSee('Sitemap: '.route('sitemap.index'))
        ->assertDontSee("Disallow: /\n", false);
    $this->get(route('home', ['locale' => 'en']))->assertHeaderMissing('X-Robots-Tag');

    // The setting overrides the environment default both ways.
    app(Settings::class)->set('seo', 'block_indexing', 'block');
    $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
    $this->get(route('home', ['locale' => 'en']))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('indexing can be allowed on staging from the setting', function () {
    app(Settings::class)->set('seo', 'block_indexing', 'allow');

    $this->get('/robots.txt')->assertSee('Sitemap:');
    $this->get(route('home', ['locale' => 'en']))->assertHeaderMissing('X-Robots-Tag');
});

test('managed redirects answer 301, 302 and 410 only for missing paths and count hits', function () {
    $permanent = Redirect::create(['from_path' => '/en/old-page', 'to_path' => '/en/services', 'status_code' => 301]);
    Redirect::create(['from_path' => '/en/campaign', 'to_path' => 'https://partner.example/offer', 'status_code' => 302]);
    Redirect::create(['from_path' => '/en/retired', 'to_path' => null, 'status_code' => 410]);
    Redirect::create(['from_path' => '/en/paused', 'to_path' => '/en/services', 'status_code' => 301, 'is_active' => false]);
    Redirect::create(['from_path' => '/en/services', 'to_path' => '/en/about', 'status_code' => 301]);

    $this->get('/en/old-page/?ref=mail')->assertStatus(301)->assertRedirect(url('/en/services').'?ref=mail');
    $this->get('/en/old-page')->assertStatus(301);
    $this->get('/en/campaign')->assertStatus(302)->assertRedirect('https://partner.example/offer');
    $this->get('/en/retired')->assertStatus(410)->assertSee(__('This page has been removed'));
    $this->get('/en/paused')->assertNotFound();
    $this->get('/en/services')->assertOk();
    $this->post('/en/old-page')->assertStatus(405);

    $permanent->refresh();
    expect($permanent->hits)->toBe(2)->and($permanent->last_hit_at)->not->toBeNull();
});

test('changing the slug of published content creates an automatic 301 and keeps redirects one hop', function (string $type) {
    [$model, $route, $field] = match ($type) {
        'page' => [Page::factory()->withTranslation('en', ['title' => 'Terms', 'slug' => 'old-slug'])->create(), 'pages.show', 'title'],
        'service' => [Service::factory()->withTranslation('en', ['title' => 'Web', 'slug' => 'old-slug'])->create(), 'services.show', 'title'],
        'portfolio' => [PortfolioProject::factory()->withTranslation('en', ['title' => 'Clinic', 'slug' => 'old-slug'])->create(), 'portfolio.show', 'title'],
        'product' => [Product::factory()->withTranslation('en', ['name' => 'ERP', 'slug' => 'old-slug'])->create(), 'products.show', 'name'],
        'post' => [Post::factory()->withTranslation('en', ['title' => 'Queues', 'slug' => 'old-slug'])->create(), 'blog.show', 'title'],
        'career' => [Career::factory()->withTranslation('en', ['title' => 'Developer', 'slug' => 'old-slug'])->create(), 'careers.show', 'title'],
    };
    $old = route($route, ['locale' => 'en', 'slug' => 'old-slug'], false);
    $new = route($route, ['locale' => 'en', 'slug' => 'new-slug'], false);
    Redirect::create(['from_path' => '/en/legacy', 'to_path' => $old, 'status_code' => 301]);

    $model->translations()->where('locale', 'en')->first()->update(['slug' => 'new-slug']);

    $redirect = Redirect::query()->where('from_path', $old)->sole();
    expect($redirect->to_path)->toBe($new)->and($redirect->is_automatic)->toBeTrue()->and($redirect->status_code)->toBe(301)
        ->and(Redirect::query()->where('from_path', '/en/legacy')->value('to_path'))->toBe($new);

    $this->get($old)->assertStatus(301)->assertRedirect(url($new));
    $this->get($new)->assertOk();

    // Renaming back makes the old URL live again: its redirect is removed instead of looping.
    $model->translations()->where('locale', 'en')->first()->update(['slug' => 'old-slug']);
    expect(Redirect::query()->where('from_path', $old)->exists())->toBeFalse()
        ->and(Redirect::query()->where('from_path', $new)->value('to_path'))->toBe($old);
})->with(['page', 'service', 'portfolio', 'product', 'post', 'career']);

test('slug changes on drafts do not create redirects', function () {
    $service = bilingualService(state: ['status' => ContentStatus::Draft]);

    $service->translations()->where('locale', 'en')->first()->update(['slug' => 'renamed']);

    expect(Redirect::query()->count())->toBe(0);
});

test('Arabic slugs redirect with percent-encoded locations', function () {
    $service = bilingualService();
    $service->translations()->where('locale', 'ar')->first()->update(['slug' => 'برمجة-الويب']);

    $this->get(route('services.show', ['locale' => 'ar', 'slug' => 'تطوير-الويب'], false))
        ->assertStatus(301)
        ->assertRedirect(route('services.show', ['locale' => 'ar', 'slug' => 'برمجة-الويب']));
});

/**
 * @return list<array<string, mixed>>
 */
function seoJsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

test('every public page carries Organization and WebSite with a SearchAction', function () {
    app(Settings::class)->set('general', 'company_name', ['en' => 'CodeFanz', 'ar' => 'كودفانز']);

    $schemas = collect(seoJsonLd($this->get(route('home', ['locale' => 'ar']))->getContent()))->keyBy('@type');

    expect($schemas['Organization']['name'])->toBe('كودفانز')
        ->and($schemas['Organization']['url'])->toBe(route('home', ['locale' => 'ar']))
        ->and($schemas['WebSite']['potentialAction']['@type'])->toBe('SearchAction')
        ->and($schemas['WebSite']['potentialAction']['target']['urlTemplate'])->toBe(route('search', ['locale' => 'ar']).'?q={search_term_string}')
        ->and($schemas['WebSite']['inLanguage'])->toBe('ar');
});

test('service and product pages carry Service, Product (without offers) and BreadcrumbList', function () {
    bilingualService();
    Product::factory()->withTranslation('en', ['name' => 'FanzERP', 'slug' => 'fanz-erp', 'tagline' => 'ERP for growing companies'])->create();

    $service = collect(seoJsonLd($this->get(route('services.show', ['locale' => 'en', 'slug' => 'web-development']))->getContent()))->keyBy('@type');
    expect($service['Service']['name'])->toBe('Web Development')
        ->and($service['Service']['url'])->toBe(route('services.show', ['locale' => 'en', 'slug' => 'web-development']))
        ->and($service['BreadcrumbList']['itemListElement'])->toHaveCount(3)
        ->and($service['BreadcrumbList']['itemListElement'][2])->toMatchArray(['position' => 3, 'name' => 'Web Development']);

    $product = collect(seoJsonLd($this->get(route('products.show', ['locale' => 'en', 'slug' => 'fanz-erp']))->getContent()))->keyBy('@type');
    expect($product['Product']['name'])->toBe('FanzERP')
        ->and($product['Product'])->not->toHaveKey('offers')
        ->and($product)->toHaveKey('BreadcrumbList');
});

test('structured data escapes values so they cannot break out of the script element', function () {
    $html = (string) app(StructuredData::class)->script(app(StructuredData::class)->service('</script><script>alert("x")</script> & \'quotes\'', null, 'https://example.com/a'));

    expect($html)->not->toContain('</script><script>')
        ->and(substr_count($html, '</script>'))->toBe(1)
        ->and(seoJsonLd($html)[0]['name'])->toBe('</script><script>alert("x")</script> & \'quotes\'');

    bilingualService('xss', 'xss-ar');
    Service::query()->first()->translations()->where('locale', 'en')->update(['title' => 'Evil </script><script>alert(1)</script>']);
    $response = $this->get(route('services.show', ['locale' => 'en', 'slug' => 'xss']))->assertOk();
    expect($response->getContent())->not->toContain('<script>alert(1)</script>');
    collect(seoJsonLd($response->getContent()))->each(fn (array $schema) => expect($schema)->toHaveKey('@type'));
});

test('site search includes published articles in the current language', function () {
    Post::factory()->withTranslation('en', ['title' => 'Laravel queues in production', 'slug' => 'laravel-queues'])->withTranslation('ar', ['title' => 'طوابير لارافيل', 'slug' => 'طوابير'])->create();
    Post::factory()->draft()->withTranslation('en', ['title' => 'Laravel draft notes', 'slug' => 'laravel-draft'])->create();

    $this->get(route('search', ['locale' => 'en', 'q' => 'laravel']))->assertOk()
        ->assertSee(__('Blog'))
        ->assertSee('Laravel queues in production')
        ->assertSee(route('blog.show', ['locale' => 'en', 'slug' => 'laravel-queues']), false)
        ->assertDontSee('Laravel draft notes');

    $this->get(route('search', ['locale' => 'ar', 'q' => 'طوابير']))->assertOk()->assertSee('طوابير لارافيل')->assertSee('dir="rtl"', false);
});
