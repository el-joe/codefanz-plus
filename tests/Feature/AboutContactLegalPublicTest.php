<?php

use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\Role;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Statistic;
use App\Models\User;
use App\Services\Settings;
use Database\Seeders\DemoContentSeeder;

function aboutSection(PageSectionType $type, array $translations): PageSection
{
    PageSection::ensureSections($type->page());
    $section = PageSection::query()->where('type', $type)->sole();

    foreach ($translations as $locale => $values) {
        $section->translations()->create(['locale' => $locale, ...$values]);
    }

    return $section;
}

test('the about page shows default intro and mission but no unwritten story, values or team', function () {
    $this->get('/en/about')
        ->assertSuccessful()
        ->assertSee('<h1', false)
        ->assertSee('Engineering digital products that last')
        ->assertSee('data-section="about_mission"', false)
        ->assertDontSee('data-section="about_story"', false)
        ->assertDontSee('data-section="about_values"', false)
        ->assertDontSee('data-section="about_team"', false)
        ->assertSee(route('quote', ['locale' => 'en']), false);
});

test('the about page renders editable story, values and disciplines in english and arabic', function () {
    aboutSection(PageSectionType::AboutStory, ['en' => ['subheading' => 'We started small.'], 'ar' => ['subheading' => 'بدأنا صغاراً.']]);
    aboutSection(PageSectionType::AboutValues, ['en' => ['items' => [['title' => 'Integrity', 'text' => 'Honest estimates.']]], 'ar' => ['items' => [['title' => 'النزاهة', 'text' => null]]]]);
    aboutSection(PageSectionType::AboutTeam, ['en' => ['items' => [['title' => 'QA & DevOps', 'text' => 'Testing']]]]);
    Statistic::factory()->withTranslation('en', ['label' => 'Projects delivered'])->create();

    $this->get('/en/about')
        ->assertSuccessful()
        ->assertSeeInOrder(['We started small.', 'Projects delivered', 'Integrity', 'Honest estimates.', 'QA &amp; DevOps'], false)
        ->assertSee('hreflang="ar"', false);

    $this->get('/ar/about')
        ->assertSuccessful()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee('بدأنا صغاراً.')
        ->assertSee('النزاهة')
        ->assertDontSee('data-section="about_team"', false);
});

test('hidden about sections are not rendered', function () {
    $story = aboutSection(PageSectionType::AboutStory, ['en' => ['subheading' => 'Hidden story text.']]);
    $story->update(['is_visible' => false]);

    $this->get('/en/about')->assertSuccessful()->assertDontSee('Hidden story text.');
});

test('careers perks appear on the careers page once written', function () {
    $this->get('/en/careers')->assertSuccessful()->assertDontSee('data-section="careers_perks"', false);

    aboutSection(PageSectionType::CareersPerks, ['en' => ['items' => [['title' => 'Remote-friendly', 'text' => 'Hybrid roles.']]], 'ar' => ['items' => [['title' => 'عمل عن بعد', 'text' => null]]]]);

    $this->get('/en/careers')->assertSuccessful()->assertSee('Why work with us')->assertSee('Remote-friendly');
    $this->get('/ar/careers')->assertSuccessful()->assertSee('dir="rtl"', false)->assertSee('عمل عن بعد');
});

test('the contact page lists only configured channels', function () {
    $this->get('/en/contact')
        ->assertSuccessful()
        ->assertDontSee('data-channel="email"', false)
        ->assertDontSee('data-channel="whatsapp"', false)
        ->assertDontSee('data-channel="phone"', false)
        ->assertDontSee('data-channel="meeting"', false)
        ->assertSee('data-channel="assistant"', false)
        ->assertSee('href="'.route('assistant', ['locale' => 'en']).'"', false)
        ->assertDontSee('Coming soon');

    $settings = app(Settings::class);
    $settings->set('contact', 'email', 'hello@example.com');
    $settings->set('contact', 'whatsapp', '+201000000000');
    $settings->set('contact', 'phone', '+20 100 000 0000');
    $settings->set('contact', 'meeting_url', 'https://cal.example.com/codefanz');

    $this->get('/en/contact')
        ->assertSuccessful()
        ->assertSeeInOrder(['data-channel="email"', 'data-channel="whatsapp"', 'data-channel="phone"', 'data-channel="meeting"', 'data-channel="assistant"'], false)
        ->assertSee('href="mailto:hello@example.com"', false)
        ->assertSee('href="https://wa.me/201000000000"', false)
        ->assertSee('href="tel:+201000000000"', false)
        ->assertSee('href="https://cal.example.com/codefanz"', false);

    $this->get('/ar/contact')->assertSuccessful()->assertSee('dir="rtl"', false)->assertSee('أرسل لنا رسالة');
});

test('the meeting link setting only accepts https urls', function () {
    $admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();

    $this->actingAs($admin)->put(route('admin.settings.update', 'contact'), ['settings' => ['meeting_url' => 'http://cal.example.com']])
        ->assertSessionHasErrors('settings.meeting_url');
});

test('the contact page preselects the topic and prefills a reference', function () {
    $this->get('/en/contact?topic=demo&ref='.urlencode('FanzERP <b>'))
        ->assertSuccessful()
        ->assertSee('<option value="demo" selected', false)
        ->assertSee('FanzERP:', false)
        ->assertDontSee('<b>', false);

    $this->get('/en/contact?topic=unknown')->assertSuccessful()->assertSee('<option value="quote" selected', false);
});

test('legal pages render numbered clauses with a contents sidebar', function () {
    Page::factory()->withTranslation('en', [
        'title' => 'Privacy Policy', 'slug' => 'privacy-policy',
        'body' => "Intro.\n\n## Information we collect\n\nContact details.\n\n## 2. How we use it\n\nTo reply.",
    ])->withTranslation('ar', ['title' => 'سياسة الخصوصية', 'slug' => 'سياسة-الخصوصية', 'body' => "## المعلومات التي نجمعها\n\nنص."])
        ->create(['template' => 'legal']);

    $html = $this->get('/en/privacy-policy')
        ->assertSuccessful()
        ->assertSee('Last updated')
        ->assertSee('<a href="#clause-1"', false)
        ->assertSee('<a href="#clause-2"', false)
        ->assertSee('<h2 id="clause-1" tabindex="-1"><span class="clause-number">1.</span> Information we collect</h2>', false)
        ->assertSee('<span class="clause-number">2.</span> How we use it</h2>', false)
        ->getContent();

    expect(substr_count($html, 'id="clause-'))->toBe(2)
        ->and($html)->not->toContain('2. 2.');

    $this->get('/ar/'.rawurlencode('سياسة-الخصوصية'))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('<a href="#clause-1"', false)
        ->assertSee('المعلومات التي نجمعها');
});

test('published legal pages fill the footer legal links until the legal menu is set up', function () {
    Page::factory()->withTranslation('en', ['title' => 'Terms & Conditions', 'slug' => 'terms'])->create(['template' => 'legal']);
    Page::factory()->withTranslation('en', ['title' => 'Draft Legal', 'slug' => 'draft-legal'])->create(['template' => 'legal', 'status' => 'draft']);

    $this->get('/en/about')
        ->assertSuccessful()
        ->assertSee(route('pages.show', ['locale' => 'en', 'slug' => 'terms']), false)
        ->assertDontSee('Draft Legal');
});

test('the demo seeder creates draft legal pages marked for legal review', function () {
    $this->seed(DemoContentSeeder::class);

    $legal = Page::query()->where('template', 'legal')->with('translations')->get();

    expect($legal)->toHaveCount(2)
        ->and($legal->every(fn (Page $page) => $page->status === PageStatus::Draft))->toBeTrue()
        ->and($legal->first()->translation('en')->body)->toContain('Draft — requires legal review')
        ->and($legal->first()->translation('ar')->body)->toContain('مسودة');

    $this->get('/en/privacy-policy')->assertNotFound();
});
