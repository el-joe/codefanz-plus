<?php

use App\Enums\MenuLocation;
use App\Services\MenuResolver;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Support\Facades\URL;

afterEach(function () {
    URL::forceRootUrl(null);
});

test('a menu cached under one host renders links for the current host', function () {
    URL::defaults(['locale' => 'en']);

    URL::forceRootUrl('http://built-elsewhere.test');
    app(MenuResolver::class)->resolveOrFallback(MenuLocation::Header, 'en');

    URL::forceRootUrl('http://codefanz.test');
    $items = app(MenuResolver::class)->resolveOrFallback(MenuLocation::Header, 'en');

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item['url'])->toStartWith('http://codefanz.test/')->not->toContain('built-elsewhere');
    }
});

test('a sitemap cached under one host is never served for another host', function () {
    URL::forceRootUrl('http://built-elsewhere.test');
    app(SitemapBuilder::class)->index();

    URL::forceRootUrl('http://codefanz.test');

    expect(app(SitemapBuilder::class)->index())->not->toContain('built-elsewhere');
});
