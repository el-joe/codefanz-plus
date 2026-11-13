<?php

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Service;
use App\Models\User;
use App\Services\MenuResolver;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
});

/**
 * @return array<string, mixed>
 */
function menuItemPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'link_type' => 'route',
        'route_name' => 'portfolio.index',
        'is_visible' => '1',
        'sort_order' => 0,
        'translations' => ['en' => ['label' => 'Portfolio'], 'ar' => ['label' => 'أعمالنا']],
    ], $overrides);
}

test('staff without content permission cannot manage menus', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();

    $this->actingAs($seo)->get(route('admin.menus.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.menus.items.store', 'header'), menuItemPayload())->assertForbidden();
});

test('unknown menu locations are not found', function () {
    $this->actingAs($this->editor)->get('/admin/menus/sidebar')->assertNotFound();
});

test('an editor adds route, content and external items', function () {
    $service = Service::factory()->withTranslation('en', ['title' => 'Web Development'])->create();

    $this->actingAs($this->editor)->get(route('admin.menus.index'))->assertSuccessful()->assertSee('Header navigation');
    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'header'), menuItemPayload(['route_query' => 'category=saas&locale=fr']))->assertRedirect(route('admin.menus.show', 'header'));
    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'header'), menuItemPayload(['link_type' => 'linkable', 'linkable' => 'service:'.$service->id, 'translations' => ['en' => ['label' => 'Web']]]))->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'footer_resources'), menuItemPayload(['link_type' => 'url', 'url' => 'https://status.example.com', 'open_in_new_tab' => '1', 'translations' => ['en' => ['label' => 'Status']]]))->assertSessionHasNoErrors();

    [$route, $linkable, $external] = MenuItem::query()->with('menu')->orderBy('id')->get()->all();

    expect($route->link_type)->toBe(MenuLinkType::Route)
        ->and($route->route_params)->toBe(['category' => 'saas'])
        ->and($linkable->linkable_type)->toBe('service')
        ->and($linkable->linkable_id)->toBe($service->id)
        ->and($external->menu->location)->toBe(MenuLocation::FooterResources)
        ->and($external->open_in_new_tab)->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'menu_item.created')->count())->toBe(3);

    $this->actingAs($this->editor)->get(route('admin.menus.show', 'header'))->assertSuccessful()->assertSee('Portfolio')->assertSee('Web Development');
    $this->actingAs($this->editor)->get(route('admin.menus.items.edit', ['header', $linkable]))->assertSuccessful();
});

test('external links must be https and link targets must exist', function (array $payload, string $field) {
    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'header'), menuItemPayload($payload))->assertSessionHasErrors($field);

    expect(MenuItem::query()->count())->toBe(0);
})->with([
    'plain http' => [['link_type' => 'url', 'url' => 'http://example.com'], 'url'],
    'javascript' => [['link_type' => 'url', 'url' => 'javascript:alert(1)'], 'url'],
    'missing url' => [['link_type' => 'url', 'url' => ''], 'url'],
    'unknown route' => [['route_name' => 'admin.dashboard'], 'route_name'],
    'missing content' => [['link_type' => 'linkable', 'linkable' => 'service:999'], 'linkable'],
    'disallowed content type' => [['link_type' => 'linkable', 'linkable' => 'user:1'], 'linkable'],
    'missing default label' => [['translations' => ['en' => ['label' => '']]], 'translations.en.label'],
]);

test('menus are limited to two levels and footers stay flat', function () {
    $parent = MenuItem::factory()->labelled(['en' => 'Services'])->create();
    $child = MenuItem::factory()->labelled(['en' => 'Web'])->create(['parent_id' => $parent->id]);
    $footer = MenuItem::factory()->in(MenuLocation::FooterCompany)->labelled(['en' => 'About'])->create();

    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'header'), menuItemPayload(['parent_id' => $parent->id]))->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'header'), menuItemPayload(['parent_id' => $child->id]))->assertSessionHasErrors('parent_id');
    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'header'), menuItemPayload(['parent_id' => $footer->id]))->assertSessionHasErrors('parent_id');
    $this->actingAs($this->editor)->post(route('admin.menus.items.store', 'footer_company'), menuItemPayload(['parent_id' => $footer->id]))->assertSessionHasErrors('parent_id');

    $other = MenuItem::factory()->labelled(['en' => 'Portfolio'])->create();
    $this->actingAs($this->editor)->put(route('admin.menus.items.update', ['header', $parent]), menuItemPayload(['parent_id' => $other->id]))->assertSessionHasErrors('parent_id');
    $this->actingAs($this->editor)->put(route('admin.menus.items.update', ['header', $parent]), menuItemPayload(['parent_id' => $parent->id]))->assertSessionHasErrors('parent_id');
});

test('items are scoped to their menu', function () {
    $footer = MenuItem::factory()->in(MenuLocation::FooterCompany)->labelled(['en' => 'About'])->create();

    $this->actingAs($this->editor)->get(route('admin.menus.items.edit', ['header', $footer]))->assertNotFound();
});

test('reordering and deleting items bust the menu cache', function () {
    $first = MenuItem::factory()->labelled(['en' => 'First'])->create(['sort_order' => 0, 'route_name' => 'portfolio.index']);
    $second = MenuItem::factory()->labelled(['en' => 'Second'])->create(['sort_order' => 1, 'route_name' => 'products.index']);

    $resolver = app(MenuResolver::class);
    expect(array_column($resolver->resolve(MenuLocation::Header, 'en'), 'label'))->toBe(['First', 'Second']);

    $this->actingAs($this->editor)->post(route('admin.menus.items.move', ['header', $second]), ['direction' => 'up'])->assertSessionHasNoErrors();
    expect(array_column($resolver->resolve(MenuLocation::Header, 'en'), 'label'))->toBe(['Second', 'First']);

    $this->actingAs($this->editor)->delete(route('admin.menus.items.destroy', ['header', $second]))->assertRedirect(route('admin.menus.show', 'header'));
    expect(array_column($resolver->resolve(MenuLocation::Header, 'en'), 'label'))->toBe(['First'])
        ->and(AuditLog::query()->whereIn('operation', ['menu_item.moved', 'menu_item.deleted'])->count())->toBe(2)
        ->and(Menu::query()->where('location', 'header')->count())->toBe(1);
});
