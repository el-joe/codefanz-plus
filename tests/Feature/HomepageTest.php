<?php

use App\Enums\PageSectionType;
use App\Models\Faq;
use App\Models\PageSection;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Statistic;
use App\Models\Testimonial;

function sectionOf(PageSectionType $type): PageSection
{
    PageSection::ensureHomeSections();

    return PageSection::query()->home()->where('type', $type)->sole();
}

test('the homepage renders without seeded sections or content', function () {
    $this->get('/en')
        ->assertSuccessful()
        ->assertSee('Turning Ideas Into')
        ->assertSee('data-section="hero"', false)
        ->assertSee('data-section="cta"', false)
        ->assertDontSee('data-section="statistics"', false)
        ->assertDontSee('data-section="testimonials"', false)
        ->assertDontSee('data-section="latest_articles"', false);
});

test('sections follow the managed order and visibility', function () {
    Service::factory()->withTranslation('en', ['title' => 'Web Development'])->create();
    Statistic::factory()->withTranslation('en', ['label' => 'Projects delivered', 'suffix' => '+'])->create(['value' => 120]);

    $statistics = sectionOf(PageSectionType::Statistics);
    $statistics->update(['sort_order' => 0]);
    sectionOf(PageSectionType::Hero)->update(['sort_order' => 1]);

    $this->get('/en')->assertSeeInOrder(['data-section="statistics"', 'data-section="hero"', 'data-section="services"'], false);

    sectionOf(PageSectionType::Services)->update(['is_visible' => false]);

    $this->get('/en')->assertDontSee('data-section="services"', false)->assertDontSee('Web Development');
});

test('statistics come only from published admin figures', function () {
    Statistic::factory()->withTranslation('en', ['label' => 'Projects delivered', 'suffix' => '+'])->create(['value' => 120]);
    Statistic::factory()->draft()->withTranslation('en', ['label' => 'Draft figure'])->create(['value' => 9]);
    Statistic::factory()->withTranslation('en', ['label' => 'Milestones on time', 'suffix' => '%'])->create(['value' => 98.5]);

    $this->get('/en')
        ->assertSee('120+')
        ->assertSee('Projects delivered')
        ->assertSee('98.5%')
        ->assertDontSee('Draft figure');
});

test('custom copy and live data render in english and arabic', function () {
    $hero = sectionOf(PageSectionType::Hero);
    $hero->translations()->create(['locale' => 'en', 'heading' => 'We build software that lasts', 'points' => ['Free estimate']]);
    $hero->translations()->create(['locale' => 'ar', 'heading' => 'نبني برمجيات تدوم']);
    $process = sectionOf(PageSectionType::Process);
    $process->translations()->create(['locale' => 'en', 'items' => [['title' => 'Discover', 'text' => 'Workshops']]]);

    PortfolioProject::factory()->featured()->withTranslation('en', ['title' => 'Featured Clinic'])->withTranslation('ar', ['title' => 'عيادة مميزة', 'slug' => 'عيادة-مميزة'])->create();
    Testimonial::factory()->featured()->withTranslation('en', ['quote' => 'Great partner.'])->withTranslation('ar', ['quote' => 'شريك رائع.'])->create();
    Faq::factory()->withTranslation('en', ['question' => 'Do I own the code?'])->create();

    $this->get('/en')
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertSee('We build software that lasts')
        ->assertSee('Free estimate')
        ->assertSee('Discover')
        ->assertSee('Featured Clinic')
        ->assertSee('Great partner.')
        ->assertSee('Do I own the code?');

    $this->get('/ar')
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('نبني برمجيات تدوم')
        ->assertSee('عيادة مميزة')
        ->assertSee('شريك رائع.')
        ->assertDontSee('Discover')
        ->assertDontSee('data-section="process"', false)
        ->assertDontSee('Do I own the code?');
});

test('testimonials without consent or not featured never reach the homepage', function () {
    Testimonial::factory()->featured()->withoutConsent()->withTranslation('en', ['quote' => 'No consent quote'])->create();
    Testimonial::factory()->withTranslation('en', ['quote' => 'Not featured quote'])->create();
    Testimonial::factory()->featured()->draft()->withTranslation('en', ['quote' => 'Draft quote'])->create();

    $this->get('/en')
        ->assertDontSee('No consent quote')
        ->assertDontSee('Not featured quote')
        ->assertDontSee('Draft quote')
        ->assertDontSee('data-section="testimonials"', false);
});

test('section limits cap the number of items', function () {
    Service::factory()->count(4)->withTranslation('en')->create();
    sectionOf(PageSectionType::Services)->update(['settings' => ['limit' => 2]]);

    $html = $this->get('/en')->getContent();
    preg_match('#data-section="services".*?</section>#s', $html, $match);

    expect(substr_count($match[0], route('services.index', ['locale' => 'en']).'/'))->toBe(2);
});
