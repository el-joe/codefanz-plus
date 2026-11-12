<?php

use App\Enums\ProjectVisibility;
use App\Models\Career;
use App\Models\Media;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Product;
use App\Models\Service;

/**
 * Creates one bilingual record per detail route and returns [english path, arabic path].
 *
 * @return array{0: string, 1: string}
 */
function seoPaths(string $route): array
{
    $make = fn (string $factory, array $en, array $ar) => $factory::factory()->withTranslation('en', $en)->withTranslation('ar', $ar)->create();

    match ($route) {
        'services.show' => $make(Service::class, ['title' => 'Web Development', 'slug' => 'web'], ['title' => 'تطوير الويب', 'slug' => 'ويب']),
        'portfolio.show' => $make(PortfolioProject::class, ['title' => 'ClinicFlow', 'slug' => 'web'], ['title' => 'كلينك', 'slug' => 'ويب']),
        'products.show' => $make(Product::class, ['name' => 'FanzERP', 'slug' => 'web'], ['name' => 'فانز', 'slug' => 'ويب']),
        'careers.show' => $make(Career::class, ['title' => 'Laravel Developer', 'slug' => 'web'], ['title' => 'مطور', 'slug' => 'ويب']),
        'pages.show' => Page::factory()->withTranslation('en', ['title' => 'Terms', 'slug' => 'web'])->withTranslation('ar', ['title' => 'الشروط', 'slug' => 'ويب'])->create(['template' => 'legal']),
        default => null,
    };

    $parameters = str_ends_with($route, '.show') ? [['slug' => 'web'], ['slug' => 'ويب']] : [[], []];

    return [route($route, ['locale' => 'en', ...$parameters[0]], false), route($route, ['locale' => 'ar', ...$parameters[1]], false)];
}

test('every milestone 4 public route has description, canonical, hreflang and OpenGraph tags', function (string $route) {
    [$english, $arabic] = seoPaths($route);

    foreach (['en' => $english, 'ar' => $arabic] as $locale => $path) {
        $response = $this->get($path)->assertSuccessful();
        $url = url($path);

        $response
            ->assertSee('<meta name="description" content="', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('hreflang="en"', false)
            ->assertSee('hreflang="ar"', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertSee('<meta property="og:title" content="', false)
            ->assertSee('<meta property="og:description" content="', false)
            ->assertSee('<meta property="og:url" content="'.$url.'">', false)
            ->assertSee('<meta property="og:locale" content="'.($locale === 'en' ? 'en_US' : 'ar_AR').'">', false)
            ->assertSee('<meta property="og:locale:alternate" content="'.($locale === 'en' ? 'ar_AR' : 'en_US').'">', false)
            ->assertDontSee('content="noindex"', false);
    }
})->with([
    'home', 'services.index', 'services.show', 'technologies.index', 'industries.index', 'portfolio.index', 'portfolio.show',
    'products.index', 'products.show', 'careers.index', 'careers.show', 'faq.index', 'testimonials.index', 'about', 'contact', 'pages.show',
]);

test('listing pages have unique titles', function () {
    $titles = collect(['home', 'services.index', 'technologies.index', 'industries.index', 'portfolio.index', 'products.index', 'careers.index', 'faq.index', 'testimonials.index', 'about', 'contact'])
        ->map(function (string $route): string {
            preg_match('#<title>(.*?)</title>#s', $this->get(route($route, ['locale' => 'en']))->getContent(), $match);

            return $match[1];
        });

    expect($titles->unique())->toHaveCount($titles->count());
});

test('a cover image becomes the OpenGraph image', function () {
    $service = Service::factory()->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web'])->create();
    $media = Media::factory()->create();
    $media->variants()->create(['format' => 'webp', 'width' => 640, 'height' => 360, 'disk' => 'public', 'path' => "media/{$media->uuid}/640.webp", 'size' => 1000]);
    $media->variants()->create(['format' => 'webp', 'width' => 1280, 'height' => 720, 'disk' => 'public', 'path' => "media/{$media->uuid}/1280.webp", 'size' => 2000]);
    $service->syncMediaCollection('cover', [$media->id]);

    $this->get('/en/services/web')
        ->assertSuccessful()
        ->assertSee('<meta property="og:image" content="'.url("/storage/media/{$media->uuid}/1280.webp").'">', false)
        ->assertSee('<meta property="og:image:width" content="1280">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

test('unlisted projects are reachable but not indexed', function () {
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Quiet Project', 'slug' => 'quiet'])->create(['visibility' => ProjectVisibility::Unlisted]);

    $this->get('/en/portfolio/quiet')->assertSuccessful()->assertSee('<meta name="robots" content="noindex">', false);
});
