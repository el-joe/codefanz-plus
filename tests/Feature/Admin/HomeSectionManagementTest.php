<?php

use App\Enums\PageSectionType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\PageSection;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
});

function homeSection(PageSectionType $type): PageSection
{
    PageSection::ensureHomeSections();

    return PageSection::query()->home()->where('type', $type)->sole();
}

test('staff without content permission cannot manage homepage sections', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();

    $this->actingAs($seo)->get(route('admin.home-sections.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.home-sections.visibility', homeSection(PageSectionType::Hero)))->assertForbidden();
});

test('the section manager lists every section type once, in order', function () {
    $this->actingAs($this->editor)->get(route('admin.home-sections.index'))
        ->assertSuccessful()
        ->assertSeeInOrder(['Hero', 'Services', 'Technologies', 'Featured projects', 'Statistics', 'Testimonials', 'Latest articles', 'Call to action'])
        ->assertDontSee('Coming soon');

    $this->actingAs($this->editor)->get(route('admin.home-sections.index'));

    expect(PageSection::query()->count())->toBe(count(PageSectionType::forPage(PageSection::HOME)))
        ->and(PageSection::query()->where('type', 'latest_articles')->value('is_visible'))->toBeTruthy();
});

test('an editor edits section copy, items and options per language', function () {
    $section = homeSection(PageSectionType::Process);

    $this->actingAs($this->editor)->get(route('admin.home-sections.edit', $section))->assertSuccessful()->assertSee('A clear process from idea to launch');

    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $section), [
        'is_visible' => '1',
        'translations' => [
            'en' => ['heading' => 'How we deliver', 'eyebrow' => ''],
            'ar' => ['heading' => 'كيف ننجز'],
        ],
        'items' => [
            ['translations' => ['en' => ['title' => 'Discover', 'text' => 'Workshops.'], 'ar' => ['title' => 'الاستكشاف']]],
            ['translations' => ['en' => ['title' => 'Build'], 'ar' => ['title' => '']]],
        ],
    ])->assertSessionHasNoErrors();

    $section->load('translations');
    expect($section->exactTranslation('en')->heading)->toBe('How we deliver')
        ->and($section->exactTranslation('en')->items)->toEqual([['title' => 'Discover', 'text' => 'Workshops.'], ['title' => 'Build', 'text' => null]])
        ->and($section->exactTranslation('ar')->items)->toEqual([['title' => 'الاستكشاف', 'text' => null]])
        ->and(AuditLog::query()->where('operation', 'page_section.updated')->exists())->toBeTrue();

    // Clearing every field of a language removes its custom copy so the default wording returns.
    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $section), ['is_visible' => '1', 'translations' => ['en' => ['heading' => 'How we deliver'], 'ar' => ['heading' => '']]]);
    expect($section->translations()->pluck('locale')->all())->toBe(['en']);
});

test('section options are validated per type', function () {
    $services = homeSection(PageSectionType::Services);
    $hero = homeSection(PageSectionType::Hero);

    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $services), ['translations' => ['en' => []], 'limit' => 50])->assertSessionHasErrors('limit');
    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $services), ['translations' => ['en' => []], 'cta_url' => 'https://example.com'])->assertSessionHasErrors('cta_url');
    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $hero), ['translations' => ['en' => []], 'cta_url' => 'javascript:alert(1)'])->assertSessionHasErrors('cta_url');
    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $hero), ['translations' => ['en' => []], 'cta_url' => '//evil.example'])->assertSessionHasErrors('cta_url');

    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $hero), ['is_visible' => '1', 'translations' => ['en' => []], 'cta_url' => '/en/portfolio'])->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $services), ['is_visible' => '1', 'translations' => ['en' => []], 'limit' => 3])->assertSessionHasNoErrors();

    expect($hero->fresh()->ctaUrl())->toBe('/en/portfolio')
        ->and($services->fresh()->limit())->toBe(3);
});

test('sections can be hidden and reordered', function () {
    $hero = homeSection(PageSectionType::Hero);
    $services = homeSection(PageSectionType::Services);

    $this->actingAs($this->editor)->post(route('admin.home-sections.visibility', $services))->assertSessionHasNoErrors();
    expect($services->fresh()->is_visible)->toBeFalse();

    $this->actingAs($this->editor)->post(route('admin.home-sections.move', $services), ['direction' => 'up'])->assertSessionHasNoErrors();
    expect($services->fresh()->sort_order)->toBeLessThan($hero->fresh()->sort_order);

    $this->actingAs($this->editor)->post(route('admin.home-sections.move', $services), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    expect(AuditLog::query()->whereIn('operation', ['page_section.hidden', 'page_section.moved'])->count())->toBe(2);
});

test('the section manager switches between the homepage, about and careers pages', function () {
    $this->actingAs($this->editor)->get(route('admin.home-sections.index', ['page' => 'about']))
        ->assertSuccessful()
        ->assertSeeInOrder(['Introduction', 'Our story', 'Mission', 'Values', 'Team disciplines'])
        ->assertDontSee('Featured projects');

    $this->actingAs($this->editor)->get(route('admin.home-sections.index', ['page' => 'careers']))->assertSuccessful()->assertSee('Why work with us');
    $this->actingAs($this->editor)->get(route('admin.home-sections.index', ['page' => 'unknown']))->assertSuccessful()->assertSee('Hero');

    expect(PageSection::query()->where('page', 'about')->count())->toBe(5)
        ->and(PageSection::query()->where('page', 'careers')->count())->toBe(1);
});

test('an editor writes about values and they appear on the about page', function () {
    PageSection::ensureSections(PageSection::ABOUT);
    $values = PageSection::query()->where('type', PageSectionType::AboutValues)->sole();

    $this->actingAs($this->editor)->get(route('admin.home-sections.edit', $values))->assertSuccessful()->assertSee('What guides every project');

    $this->actingAs($this->editor)->put(route('admin.home-sections.update', $values), [
        'is_visible' => '1',
        'translations' => ['en' => ['heading' => ''], 'ar' => []],
        'items' => [['translations' => ['en' => ['title' => 'Integrity', 'text' => 'Honest estimates.'], 'ar' => ['title' => 'النزاهة']]]],
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.home-sections.edit', $values));

    $this->get('/en/about')->assertSee('Integrity')->assertSee('Honest estimates.');

    // Moving a section only reorders sections of the same page.
    $this->actingAs($this->editor)->post(route('admin.home-sections.move', $values), ['direction' => 'up'])->assertRedirect();
    expect(PageSection::query()->where('page', 'about')->orderBy('sort_order')->pluck('type')->map->value->all())
        ->toBe(['about_intro', 'about_story', 'about_values', 'about_mission', 'about_team']);
});
