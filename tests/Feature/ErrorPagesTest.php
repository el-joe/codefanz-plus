<?php

use App\Models\Service;
use Illuminate\Support\Facades\Route;

test('the 404 page renders in english with search and popular links', function () {
    $this->get('/en/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('<html lang="en" dir="ltr"', false)
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('This page can’t be found')
        ->assertSee('dir="ltr">404</p>', false)
        ->assertSee('action="'.route('search', ['locale' => 'en']).'"', false)
        ->assertSee(route('contact', ['locale' => 'en', 'topic' => 'quote']), false)
        ->assertSee('href="#main"', false);
});

test('the 404 page follows the url language, then the browser language', function () {
    $this->get('/ar/'.rawurlencode('غير-موجودة'))
        ->assertNotFound()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee(route('search', ['locale' => 'ar']), false);

    $this->withHeader('Accept-Language', 'ar')->get('/no-such-top-level-path')
        ->assertNotFound()
        ->assertSee('<html lang="ar" dir="rtl"', false);
});

test('the 404 page falls back to the default language for unsupported prefixes and browsers', function () {
    $this->withHeader('Accept-Language', 'fr')->get('/zz/unknown')
        ->assertNotFound()
        ->assertSee('<html lang="en" dir="ltr"', false);
});

test('the 500 page shows the request reference without leaking the error', function (string $language, string $direction) {
    config(['app.debug' => false]);
    Route::get('/_test/explode', fn () => throw new RuntimeException('secret database password in message'));

    $response = $this->withHeader('Accept-Language', $language)->get('/_test/explode')
        ->assertInternalServerError()
        ->assertSee('<html lang="'.$language.'" dir="'.$direction.'"', false)
        ->assertSee('data-error-reference', false)
        ->assertSee(config('codefanz.support_email'))
        ->assertDontSee('secret database password');

    $reference = $response->headers->get('X-Request-Id');

    expect($reference)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($response->getContent())->toContain($reference);
})->with([
    'english' => ['en', 'ltr'],
    'arabic' => ['ar', 'rtl'],
]);

test('every response carries a request id header', function () {
    $this->get('/en/about')->assertHeader('X-Request-Id');
});

test('minimal error pages render for 403, 419, 429 and 503', function (int $status, string $english, string $arabic) {
    Route::get('/_test/abort/{status}', fn (int $status) => abort($status));
    Route::get('/ar/_test/abort/{status}', fn (int $status) => abort($status));

    $this->get("/_test/abort/{$status}")->assertStatus($status)->assertSee($english)->assertSee('lang="en"', false);
    $this->get("/ar/_test/abort/{$status}")->assertStatus($status)->assertSee($arabic)->assertSee('dir="rtl"', false);
})->with([
    [403, 'You don’t have access to this page', 'لا تملك صلاحية الوصول إلى هذه الصفحة'],
    [419, 'This page has expired', 'انتهت صلاحية هذه الصفحة'],
    [429, 'Too many requests', 'طلبات كثيرة جداً'],
    [503, 'We’ll be right back', 'سنعود قريباً'],
]);

test('the site search finds published content titles only', function () {
    Service::factory()->withTranslation('en', ['title' => 'Mobile App Development', 'slug' => 'mobile-apps'])->create();
    Service::factory()->draft()->withTranslation('en', ['title' => 'Mobile Draft Service'])->create();

    $this->get('/en/search?q=mobile')
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('Mobile App Development')
        ->assertDontSee('Mobile Draft Service');

    $this->get('/en/search?q=%25')->assertSuccessful()->assertDontSee('Mobile App Development');
    $this->get('/ar/search?q=mobile')->assertSuccessful()->assertSee('dir="rtl"', false)->assertDontSee('Mobile App Development');
});
