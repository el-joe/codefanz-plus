<?php

use App\Models\PortfolioProject;
use App\Services\SiteImages;
use Illuminate\Support\Facades\File;

/*
| Design v2 (docs/DESIGN_V2.md): the generated image set is served as committed AVIF/WebP/JPEG variants, the public
| layout opts into the v2 scope, and items without uploads fall back to stable generated covers.
*/

test('every generated site image variant is committed under public/images/v2', function () {
    $missing = array_values(array_filter(SiteImages::expectedFiles(), fn (string $file): bool => ! File::exists(public_path($file))));

    expect($missing)->toBe([]);

    foreach (array_keys(SiteImages::definitions()) as $name) {
        expect(File::exists(base_path(SiteImages::SOURCE_DIRECTORY."/{$name}.jpg")))->toBeTrue();
    }
});

test('the build command writes cropped variants at the defined sizes', function () {
    $output = storage_path('framework/testing/site-images-'.uniqid());

    $this->artisan('site-images:build', ['--output' => $output])->assertSuccessful();

    $hero = getimagesize("{$output}/hero-glass-shapes-640.webp");
    $fallback = getimagesize($output.'/'.basename(SiteImages::path('cover-teal-arch', SiteImages::fallbackWidth('cover-teal-arch'), 'jpg')));
    $og = getimagesize($output.'/'.SiteImages::OG_IMAGE);

    expect([$hero[0], $hero[1]])->toBe([640, SiteImages::heightFor('hero-glass-shapes', 640)])
        ->and([$fallback[0], $fallback[1]])->toBe([960, 600])
        ->and([$og[0], $og[1]])->toBe([1200, 630])
        ->and(count(File::files($output)))->toBe(count(SiteImages::expectedFiles()));

    File::deleteDirectory($output);
});

test('variant widths never upscale and fallback covers are stable per id', function () {
    expect(SiteImages::widths('hero-glass-shapes'))->toBe([480, 640, 929])
        ->and(SiteImages::widths('cover-indigo-torus'))->toBe([480, 640, 960, 1264])
        ->and(SiteImages::fallbackWidth('hero-glass-shapes'))->toBe(929)
        ->and(SiteImages::coverFor(3))->toBe('cover-indigo-torus')
        ->and(SiteImages::coverFor(4))->toBe('cover-teal-arch')
        ->and(SiteImages::coverFor(5))->toBe(SiteImages::coverFor(5))
        ->and(SiteImages::coverFor(7, SiteImages::ARTICLE_COVERS))->toBe('process-glass-steps');
});

test('the home hero serves the preloaded responsive artwork with localized alt text', function (string $locale, string $alt) {
    $response = $this->get("/{$locale}")->assertSuccessful();

    $response->assertSee('text-fg" data-site>', false)
        ->assertSee('data-site-header', false)
        ->assertSee('<link rel="preload" as="image" type="image/avif" fetchpriority="high"', false)
        ->assertSee('<source type="image/avif" srcset="'.asset('images/v2/hero-glass-shapes-480.avif').' 480w', false)
        ->assertSee('alt="'.$alt.'" decoding="sync" loading="eager"', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('width="929" height="896"', false)
        ->assertSee('href="'.route('assistant', ['locale' => $locale]).'"', false)
        ->assertDontSee('Revenue collected')
        ->assertSee('<meta property="og:image" content="'.asset('images/v2/og-default.jpg').'">', false);
})->with([
    'english' => ['en', 'Floating glass shapes in indigo and teal'],
    'arabic' => ['ar', 'أشكال زجاجية عائمة بدرجات النيلي والفيروزي'],
]);

test('project cards without an uploaded cover use the generated fallback for their id', function () {
    $project = PortfolioProject::factory()->withTranslation('en', ['title' => 'Grid Project'])->create();
    $cover = SiteImages::coverFor($project->id);

    $this->get('/en/portfolio')
        ->assertSuccessful()
        ->assertSee(asset("images/v2/{$cover}-480.avif"), false)
        ->assertSee('loading="lazy"', false)
        ->assertSee('data-reveal-stagger', false);
});

test('the 404 page shows the lost cube artwork without needing javascript', function () {
    $this->get('/en/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('data-site', false)
        ->assertSee(asset('images/v2/not-found-lost-cube-480.avif'), false)
        ->assertSee('alt="A single glass cube drifting away from its grid"', false);
});

test('inner page heroes show the image accent unless disabled', function () {
    $this->get('/en/services')->assertSuccessful()->assertSee(asset('images/v2/about-glass-stack-480.avif'), false);

    $this->get('/en/search')->assertSuccessful()->assertDontSee('about-glass-stack', false);
});

test('the admin keeps the v1 look outside the public v2 scope', function () {
    $this->get(route('login'))->assertSuccessful()->assertDontSee('data-site', false);
});
