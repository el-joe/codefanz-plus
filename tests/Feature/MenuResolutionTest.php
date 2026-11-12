<?php

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Models\Career;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Service;
use App\Services\MenuResolver;

function resolvedMenu(MenuLocation $location, string $locale): array
{
    return app(MenuResolver::class)->resolve($location, $locale);
}

test('content items resolve to the translated slug of each locale', function () {
    $service = Service::factory()
        ->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web-development'])
        ->withTranslation('ar', ['title' => 'تطوير المواقع', 'slug' => 'تطوير-المواقع'])
        ->create();

    MenuItem::factory()->labelled(['en' => 'Web', 'ar' => 'الويب'])->create([
        'link_type' => MenuLinkType::Linkable, 'route_name' => null, 'linkable_type' => 'service', 'linkable_id' => $service->id,
    ]);

    expect(resolvedMenu(MenuLocation::Header, 'en'))->toBe([['label' => 'Web', 'url' => route('services.show', ['locale' => 'en', 'slug' => 'web-development']), 'new_tab' => false, 'children' => []]])
        ->and(resolvedMenu(MenuLocation::Header, 'ar')[0]['url'])->toBe(route('services.show', ['locale' => 'ar', 'slug' => 'تطوير-المواقع']))
        ->and(resolvedMenu(MenuLocation::Header, 'ar')[0]['label'])->toBe('الويب');
});

test('route items keep their filters and switch locale', function () {
    MenuItem::factory()->labelled(['en' => 'SaaS work'])->create(['route_name' => 'portfolio.index', 'route_params' => ['category' => 'saas']]);

    expect(resolvedMenu(MenuLocation::Header, 'en')[0]['url'])->toBe(url('/en/portfolio?category=saas'))
        ->and(resolvedMenu(MenuLocation::Header, 'ar')[0]['url'])->toBe(url('/ar/portfolio?category=saas'))
        ->and(resolvedMenu(MenuLocation::Header, 'ar')[0]['label'])->toBe('SaaS work');
});

test('hidden items, their children and unavailable content are left out', function () {
    $draft = Page::factory()->withTranslation('en', ['slug' => 'draft-page'])->create(['status' => 'draft']);
    $englishOnly = Service::factory()->withTranslation('en', ['slug' => 'english-only'])->create();
    $closed = Career::factory()->closed()->withTranslation('en')->create();
    $trashed = Service::factory()->withTranslation('en', ['slug' => 'trashed'])->create();
    $trashed->delete();

    $hidden = MenuItem::factory()->hidden()->labelled(['en' => 'Hidden parent'])->create();
    MenuItem::factory()->labelled(['en' => 'Orphaned child'])->create(['parent_id' => $hidden->id]);
    foreach (['page' => $draft, 'service' => $englishOnly, 'career' => $closed] as $alias => $model) {
        MenuItem::factory()->labelled(['en' => "Link to {$alias}", 'ar' => "رابط {$alias}"])->create(['link_type' => MenuLinkType::Linkable, 'route_name' => null, 'linkable_type' => $alias, 'linkable_id' => $model->id]);
    }
    MenuItem::factory()->labelled(['en' => 'Trashed'])->create(['link_type' => MenuLinkType::Linkable, 'route_name' => null, 'linkable_type' => 'service', 'linkable_id' => $trashed->id]);
    MenuItem::factory()->labelled(['en' => 'Visible'])->create();

    expect(array_column(resolvedMenu(MenuLocation::Header, 'en'), 'label'))->toBe(['Link to service', 'Visible'])
        ->and(array_column(resolvedMenu(MenuLocation::Header, 'ar'), 'label'))->toBe(['Visible']);
});

test('only the header nests items, two levels deep', function () {
    $parent = MenuItem::factory()->labelled(['en' => 'Services'])->create();
    MenuItem::factory()->labelled(['en' => 'Child'])->create(['parent_id' => $parent->id, 'route_name' => 'technologies.index']);
    $footerParent = MenuItem::factory()->in(MenuLocation::FooterCompany)->labelled(['en' => 'Company'])->create();
    MenuItem::factory()->in(MenuLocation::FooterCompany)->labelled(['en' => 'Sneaky child'])->create(['parent_id' => $footerParent->id]);

    $header = resolvedMenu(MenuLocation::Header, 'en');

    expect($header)->toHaveCount(1)
        ->and(array_column($header[0]['children'], 'label'))->toBe(['Child'])
        ->and(array_column(resolvedMenu(MenuLocation::FooterCompany, 'en'), 'label'))->toBe(['Company']);
});

test('unsafe stored urls are never rendered', function () {
    MenuItem::factory()->url('javascript:alert(1)')->labelled(['en' => 'Bad'])->create();
    MenuItem::factory()->url('https://example.com/docs')->labelled(['en' => 'Docs'])->create(['open_in_new_tab' => true]);

    expect(resolvedMenu(MenuLocation::Header, 'en'))->toBe([['label' => 'Docs', 'url' => 'https://example.com/docs', 'new_tab' => true, 'children' => []]]);
});

test('resolved menus are cached and busted when linked content changes', function () {
    $service = Service::factory()->withTranslation('en', ['slug' => 'old-slug'])->create();
    MenuItem::factory()->labelled(['en' => 'Web'])->create(['link_type' => MenuLinkType::Linkable, 'route_name' => null, 'linkable_type' => 'service', 'linkable_id' => $service->id]);

    expect(resolvedMenu(MenuLocation::Header, 'en')[0]['url'])->toEndWith('/en/services/old-slug');

    $service->translations()->where('locale', 'en')->first()->update(['slug' => 'new-slug']);

    expect(resolvedMenu(MenuLocation::Header, 'en')[0]['url'])->toEndWith('/en/services/new-slug');

    $service->update(['status' => 'draft']);

    expect(resolvedMenu(MenuLocation::Header, 'en'))->toBe([]);
});

test('the public layout renders menus with a fallback and an accessible mobile toggle', function () {
    $this->get('/en/services')
        ->assertSuccessful()
        ->assertSee('aria-controls="mobile-nav"', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee(route('portfolio.index', ['locale' => 'en']), false);

    $parent = MenuItem::factory()->labelled(['en' => 'Our services', 'ar' => 'خدماتنا'])->create();
    MenuItem::factory()->labelled(['en' => 'Stack', 'ar' => 'التقنيات'])->create(['parent_id' => $parent->id, 'route_name' => 'technologies.index']);
    MenuItem::factory()->in(MenuLocation::FooterLegal)->url('https://example.com/terms')->labelled(['en' => 'Terms'])->create(['open_in_new_tab' => true]);

    $this->get('/en/services')
        ->assertSee('Our services')
        ->assertSee('Stack')
        ->assertSee('data-dropdown-trigger', false)
        ->assertSee('href="https://example.com/terms" target="_blank" rel="noopener noreferrer"', false);

    $this->get('/ar/services')->assertSee('خدماتنا')->assertSee(route('technologies.index', ['locale' => 'ar']), false);
});
