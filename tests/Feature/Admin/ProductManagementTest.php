<?php

use App\Enums\ContentStatus;
use App\Enums\PricingModel;
use App\Enums\ProductAvailability;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Technology;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->create();
});

/**
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'icon' => 'layers',
        'pricing_models' => ['one_time_license', 'subscription'],
        'availability' => 'beta',
        'demo_enabled' => '1',
        'docs_url' => 'https://docs.example.com/erp',
        'sort_order' => 0,
        'translations' => [
            'en' => ['name' => 'FanzERP', 'slug' => 'fanzerp', 'tagline' => 'ERP for retail.', 'description' => '## Overview', 'licensing_notes' => 'Quotes are preliminary.'],
            'ar' => ['name' => 'فانز ERP', 'slug' => 'فانز-erp'],
        ],
        'features' => [
            ['translations' => ['en' => ['title' => 'Inventory', 'description' => 'Multi-warehouse.'], 'ar' => ['title' => 'المخزون']]],
            ['translations' => ['en' => ['title' => 'Point of sale']]],
        ],
    ], $overrides);
}

test('staff without content permission cannot manage products or versions', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->create();
    $product = Product::factory()->withTranslation('en')->create();
    $version = ProductVersion::factory()->for($product)->withTranslation('en')->create();

    $this->actingAs($seo)->get(route('admin.products.index'))->assertForbidden();
    $this->actingAs($seo)->get(route('admin.products.edit', $product))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.products.store'), productPayload())->assertForbidden();
    $this->actingAs($seo)->post(route('admin.products.publish', $product))->assertForbidden();
    $this->actingAs($seo)->delete(route('admin.products.destroy', $product))->assertForbidden();
    $this->actingAs($seo)->get(route('admin.products.versions.create', $product))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.products.versions.store', $product), ['version' => '9.9', 'translations' => ['en' => ['changelog' => 'x']]])->assertForbidden();
    $this->actingAs($seo)->delete(route('admin.products.versions.destroy', [$product, $version]))->assertForbidden();
});

test('an editor creates a bilingual product with licensing models, features and screenshots', function () {
    $technology = Technology::factory()->withTranslation('en')->create();
    [$cover, $first, $second] = Media::factory()->count(3)->create()->all();

    $response = $this->actingAs($this->editor)->post(route('admin.products.store'), productPayload([
        'technology_ids' => [$technology->id],
        'cover_media_id' => $cover->id,
        'screenshot_media_ids' => [$second->id, $first->id],
    ]));

    $product = Product::query()->with(['translations', 'technologies', 'screenshots'])->sole();
    $response->assertRedirect(route('admin.products.edit', $product));

    expect($product->status)->toBe(ContentStatus::Draft)
        ->and($product->availability)->toBe(ProductAvailability::Beta)
        ->and($product->pricing_models->all())->toBe([PricingModel::OneTimeLicense, PricingModel::Subscription])
        ->and($product->exactTranslation('en')->filledFeatures())->toHaveCount(2)
        ->and($product->exactTranslation('ar')->features)->toBe([['title' => 'المخزون', 'description' => null]])
        ->and($product->screenshots->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($product->technologies->pluck('id')->all())->toBe([$technology->id])
        ->and(AuditLog::query()->where('operation', 'product.created')->where('auditable_type', 'product')->exists())->toBeTrue();

    $this->actingAs($this->editor)->get(route('admin.products.index'))->assertSuccessful()->assertSee('FanzERP');
    $this->actingAs($this->editor)->get(route('admin.products.create'))->assertSuccessful();
    $this->actingAs($this->editor)->get(route('admin.products.edit', $product))->assertSuccessful()->assertSee('dir="rtl"', false)->assertSee('Point of sale');
});

test('product input is validated', function (array $overrides, string $field) {
    Product::factory()->withTranslation('en', ['slug' => 'taken'])->create();

    $this->actingAs($this->editor)
        ->post(route('admin.products.store'), array_replace(productPayload($overrides), array_intersect_key($overrides, ['pricing_models' => true])))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate slug' => [['translations' => ['en' => ['slug' => 'taken']]], 'translations.en.slug'],
    'missing default name' => [['translations' => ['en' => ['name' => '']]], 'translations.en.name'],
    'no licensing model' => [['pricing_models' => []], 'pricing_models'],
    'unknown licensing model' => [['pricing_models' => ['fixed_price']], 'pricing_models.0'],
    'unknown availability' => [['availability' => 'retired'], 'availability'],
    'http docs url' => [['docs_url' => 'http://docs.example.com'], 'docs_url'],
    'unknown technology' => [['technology_ids' => [999]], 'technology_ids.0'],
]);

test('saving an outdated product version is rejected', function () {
    $this->actingAs($this->editor)->post(route('admin.products.store'), productPayload());
    $product = Product::query()->sole();

    $this->actingAs($this->editor)->put(route('admin.products.update', $product), productPayload(['lock_version' => 1]))->assertSessionHasNoErrors();
    $this->actingAs($this->editor)->put(route('admin.products.update', $product), productPayload(['lock_version' => 1]))->assertSessionHasErrors('lock_version');

    expect($product->fresh()->lock_version)->toBe(2);
});

test('publishing and trashing a product are audited', function () {
    $product = Product::factory()->draft()->withTranslation('en')->create();

    $this->actingAs($this->editor)->post(route('admin.products.publish', $product))->assertSessionHasNoErrors();
    expect($product->fresh()->status)->toBe(ContentStatus::Published);

    $this->actingAs($this->editor)->delete(route('admin.products.destroy', $product))->assertRedirect(route('admin.products.index'));
    expect($product->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->whereIn('operation', ['product.published', 'product.deleted'])->count())->toBe(2);
});

test('an editor manages versions with translated changelogs', function () {
    $product = Product::factory()->withTranslation('en')->create();

    $this->actingAs($this->editor)->get(route('admin.products.versions.create', $product))->assertSuccessful();
    $this->actingAs($this->editor)
        ->post(route('admin.products.versions.store', $product), ['version' => 'v2.3', 'released_at' => '2026-11-01', 'translations' => ['en' => ['changelog' => '- Offline sync'], 'ar' => ['changelog' => '- مزامنة دون اتصال']]])
        ->assertRedirect(route('admin.products.edit', $product));

    $version = ProductVersion::query()->with('translations')->sole();
    expect($version->version)->toBe('2.3')
        ->and($version->exactTranslation('ar')->changelog)->toBe('- مزامنة دون اتصال')
        ->and(AuditLog::query()->where('operation', 'product_version.created')->exists())->toBeTrue();

    $this->actingAs($this->editor)->get(route('admin.products.versions.edit', [$product, $version]))->assertSuccessful()->assertSee('Offline sync');

    $this->actingAs($this->editor)
        ->post(route('admin.products.versions.store', $product), ['version' => '2.3', 'translations' => ['en' => ['changelog' => 'dup']]])
        ->assertSessionHasErrors('version');
    $this->actingAs($this->editor)
        ->post(route('admin.products.versions.store', $product), ['version' => 'latest', 'translations' => ['en' => ['changelog' => 'x']]])
        ->assertSessionHasErrors('version');
    $this->actingAs($this->editor)
        ->post(route('admin.products.versions.store', $product), ['version' => '2.4', 'translations' => ['en' => ['changelog' => '']]])
        ->assertSessionHasErrors('translations.en.changelog');

    $this->actingAs($this->editor)
        ->put(route('admin.products.versions.update', [$product, $version]), ['version' => '2.3.1', 'translations' => ['en' => ['changelog' => '- Fixed']]])
        ->assertSessionHasNoErrors();
    expect($version->fresh()->version)->toBe('2.3.1');

    $this->actingAs($this->editor)->delete(route('admin.products.versions.destroy', [$product, $version]))->assertRedirect(route('admin.products.edit', $product));
    expect(ProductVersion::query()->count())->toBe(0);
});

test('a version cannot be reached through another product', function () {
    $product = Product::factory()->withTranslation('en')->create();
    $foreign = ProductVersion::factory()->withTranslation('en')->create();

    $this->actingAs($this->editor)->get(route('admin.products.versions.edit', [$product, $foreign]))->assertNotFound();
    $this->actingAs($this->editor)->delete(route('admin.products.versions.destroy', [$product, $foreign]))->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});
