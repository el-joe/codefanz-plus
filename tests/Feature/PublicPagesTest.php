<?php

use App\Enums\PageStatus;
use App\Models\Page;

test('a published page renders in english with alternate links', function () {
    Page::factory()
        ->withTranslation('en', ['title' => 'Privacy Policy', 'slug' => 'privacy', 'body' => "## Data we collect\n\nWe keep it short."])
        ->withTranslation('ar', ['title' => 'سياسة الخصوصية', 'slug' => 'الخصوصية'])
        ->create();

    $this->get('/en/privacy')
        ->assertSuccessful()
        ->assertSee('<html lang="en" dir="ltr"', false)
        ->assertSee('<h2>Data we collect</h2>', false)
        ->assertSee('hreflang="ar"', false)
        ->assertSee(route('pages.show', ['locale' => 'ar', 'slug' => 'الخصوصية']), false);
});

test('a published page renders in arabic right to left', function () {
    Page::factory()->withTranslation('ar', ['title' => 'سياسة الخصوصية', 'slug' => 'الخصوصية'])->create();

    $this->get('/ar/'.rawurlencode('الخصوصية'))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('سياسة الخصوصية');
});

test('drafts, trashed pages and other-language slugs are not found', function () {
    Page::factory()->withTranslation('en', ['slug' => 'draft'])->create(['status' => PageStatus::Draft]);
    Page::factory()->withTranslation('en', ['slug' => 'gone'])->create()->delete();
    Page::factory()->withTranslation('en', ['slug' => 'english-only'])->create();

    $this->get('/en/draft')->assertNotFound();
    $this->get('/en/gone')->assertNotFound();
    $this->get('/ar/english-only')->assertNotFound();
});

test('raw html in page content is stripped', function () {
    Page::factory()->withTranslation('en', [
        'slug' => 'unsafe',
        'body' => "<script>alert('x')</script>\n\n[click](javascript:alert(1))",
    ])->create();

    $this->get('/en/unsafe')
        ->assertSuccessful()
        ->assertDontSee("<script>alert('x')</script>", false)
        ->assertDontSee('javascript:alert', false);
});
