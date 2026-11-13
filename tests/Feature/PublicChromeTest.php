<?php

use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\User;

test('the public site is always light while the admin can opt into the dark theme', function () {
    $public = $this->get('/en/about')->assertSuccessful();
    $public->assertSee('<html lang="en" dir="ltr">', false)
        ->assertDontSee('codefanz-themeable', false)
        ->assertDontSee("localStorage.getItem('codefanz-theme')", false);

    $this->get('/en/this-page-does-not-exist')->assertNotFound()->assertDontSee('codefanz-theme', false);

    $admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('<meta name="codefanz-themeable" content="1">', false)
        ->assertSee("localStorage.getItem('codefanz-theme') === 'dark'", false)
        ->assertDontSee('prefers-color-scheme', false);
});

test('the header follows the Figma navbar with one Services link that opens the services panel', function (string $locale, string $languageLabel) {
    $response = $this->get("/{$locale}/about")->assertSuccessful();

    $response->assertSeeInOrder([
        route('services.index', ['locale' => $locale]),
        route('portfolio.index', ['locale' => $locale]),
        route('products.index', ['locale' => $locale]),
        route('technologies.index', ['locale' => $locale]),
        route('blog.index', ['locale' => $locale]),
        route('about', ['locale' => $locale]),
    ], false)->assertSee($languageLabel);

    // The built-in fallback has no sub-items, so Services is a plain link until an admin adds children.
    expect(substr_count($response->getContent(), 'data-nav-trigger'))->toBe(0);
})->with([
    'english' => ['en', 'عربي'],
    'arabic' => ['ar', 'English'],
]);

test('the services trigger is a single link with a disclosure panel and a mobile chevron toggle', function () {
    $parent = MenuItem::factory()->labelled(['en' => 'Services', 'ar' => 'الخدمات'])->create(['route_name' => 'services.index']);
    MenuItem::factory()->labelled(['en' => 'Web Development', 'ar' => 'تطوير المواقع'])->create(['parent_id' => $parent->id, 'route_name' => 'technologies.index']);

    $this->get('/en/about')
        ->assertSee('href="'.route('services.index', ['locale' => 'en']).'"', false)
        ->assertSee('aria-haspopup="true" aria-expanded="false" aria-controls="nav-submenu-0" data-nav-trigger', false)
        ->assertSee('All services')
        ->assertSee('aria-label="Show services"', false);

    $this->get('/ar/about')->assertSee('جميع الخدمات')->assertSee('aria-label="عرض قائمة الخدمات"', false);
});

test('the floating assistant launcher shows on public pages except the assistant itself', function () {
    $this->get('/en')->assertSuccessful()
        ->assertSee('data-assistant-launcher', false)
        ->assertSee('aria-label="Chat with our AI assistant"', false)
        ->assertSee('href="'.route('assistant', ['locale' => 'en']).'"', false);

    $this->get('/ar/about')->assertSee('aria-label="تحدث مع مساعدنا الذكي"', false);

    $this->get('/en/assistant')->assertSuccessful()->assertDontSee('data-assistant-launcher', false);
});
