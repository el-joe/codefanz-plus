<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Enums\TechnologyProficiency;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
    $this->category = TechnologyCategory::factory()->withTranslation('en', ['name' => 'Frameworks'])->create();
});

afterEach(function () {
    Technology::flushVerificationRelations();
});

/**
 * @return array<string, mixed>
 */
function technologyPayload(int $categoryId, array $overrides = []): array
{
    return array_replace_recursive([
        'technology_category_id' => $categoryId,
        'key' => 'laravel',
        'website_url' => 'https://laravel.com',
        'proficiency' => 'expertise',
        'is_visible' => '1',
        'sort_order' => 0,
        'translations' => [
            'en' => ['name' => 'Laravel', 'description' => 'PHP framework.'],
            'ar' => ['name' => 'لارافيل'],
        ],
    ], $overrides);
}

/**
 * Stand-in for the portfolio module: a published-only relation that proves delivered-project experience.
 */
function registerPublishedServicesAsVerification(): void
{
    Technology::resolveRelationUsing('deliveredWork', fn (Technology $technology) => $technology->services()->where('status', ContentStatus::Published));
    Technology::verifiedThrough('deliveredWork');
}

test('staff without content permission cannot manage technologies', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();
    $technology = Technology::factory()->create();

    $this->actingAs($seo)->get(route('admin.technologies.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.technologies.store'), technologyPayload($this->category->id))->assertForbidden();
    $this->actingAs($seo)->put(route('admin.technologies.update', $technology), technologyPayload($this->category->id))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.technology-categories.store'), ['key' => 'x', 'sort_order' => 0, 'translations' => ['en' => ['name' => 'X']]])->assertForbidden();
});

test('an editor creates and updates a technology', function () {
    $this->actingAs($this->editor)->post(route('admin.technologies.store'), technologyPayload($this->category->id))->assertSessionHasNoErrors();

    $technology = Technology::query()->with('translations')->sole();

    expect($technology->proficiency)->toBe(TechnologyProficiency::Expertise)
        ->and($technology->exactTranslation('ar')->name)->toBe('لارافيل')
        ->and(AuditLog::query()->where('operation', 'technology.created')->exists())->toBeTrue();

    $this->actingAs($this->editor)
        ->put(route('admin.technologies.update', $technology), technologyPayload($this->category->id, ['proficiency' => 'supported', 'is_visible' => '0']))
        ->assertRedirect(route('admin.technologies.edit', $technology));

    expect($technology->fresh()->proficiency)->toBe(TechnologyProficiency::Supported)
        ->and($technology->fresh()->is_visible)->toBeFalse();

    $this->actingAs($this->editor)->get(route('admin.technologies.index'))->assertSuccessful()->assertSee('Laravel');
    $this->actingAs($this->editor)->get(route('admin.technologies.edit', $technology))->assertSuccessful()->assertSee('dir="rtl"', false);
});

test('verified in projects cannot be assigned by hand', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.technologies.store'), technologyPayload($this->category->id, ['proficiency' => TechnologyProficiency::VerifiedInProjects->value]))
        ->assertSessionHasErrors('proficiency');

    expect(Technology::query()->count())->toBe(0);
});

test('technology input is validated', function (array $overrides, string $field) {
    Technology::factory()->create(['key' => 'django']);

    $this->actingAs($this->editor)
        ->post(route('admin.technologies.store'), technologyPayload($this->category->id, $overrides))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate key' => [['key' => 'django'], 'key'],
    'malformed key' => [['key' => 'Node JS'], 'key'],
    'unsafe url' => [['website_url' => 'javascript:alert(1)'], 'website_url'],
    'missing default name' => [['translations' => ['en' => ['name' => '']]], 'translations.en.name'],
    'unknown category' => [['technology_category_id' => 999], 'technology_category_id'],
]);

test('proficiency shows the assigned level until delivered work links the technology', function () {
    registerPublishedServicesAsVerification();

    $supported = Technology::factory()->create();
    $expertise = Technology::factory()->expertise()->create();
    Service::factory()->draft()->create()->technologies()->attach([$supported->id, $expertise->id]);

    $loaded = Technology::query()->withProficiency()->get()->keyBy('id');

    expect($loaded[$supported->id]->proficiency())->toBe(TechnologyProficiency::Supported)
        ->and($loaded[$expertise->id]->proficiency())->toBe(TechnologyProficiency::Expertise);

    Service::factory()->create()->technologies()->attach($supported->id);

    $loaded = Technology::query()->withProficiency()->get()->keyBy('id');

    expect($loaded[$supported->id]->proficiency())->toBe(TechnologyProficiency::VerifiedInProjects)
        ->and($loaded[$expertise->id]->proficiency())->toBe(TechnologyProficiency::Expertise)
        ->and($supported->fresh()->proficiency())->toBe(TechnologyProficiency::VerifiedInProjects)
        ->and($supported->fresh()->getRawOriginal('proficiency'))->toBe('supported');
});

test('without verification sources nothing is shown as verified', function () {
    $technology = Technology::factory()->expertise()->create();
    Service::factory()->create()->technologies()->attach($technology->id);

    expect(Technology::query()->withProficiency()->find($technology->id)->proficiency())->toBe(TechnologyProficiency::Expertise);
});

test('categories are managed and cannot be deleted while in use', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.technology-categories.store'), ['key' => 'mobile', 'sort_order' => 2, 'translations' => ['en' => ['name' => 'Mobile'], 'ar' => ['name' => 'الجوال']]])
        ->assertRedirect(route('admin.technologies.index'));

    $mobile = TechnologyCategory::query()->where('key', 'mobile')->sole();
    Technology::factory()->create(['technology_category_id' => $this->category->id]);

    $this->actingAs($this->editor)->get(route('admin.technology-categories.edit', $mobile))->assertSuccessful()->assertSee('الجوال');
    $this->actingAs($this->editor)->delete(route('admin.technology-categories.destroy', $this->category))->assertSessionHasErrors('category');
    $this->actingAs($this->editor)->delete(route('admin.technology-categories.destroy', $mobile))->assertRedirect(route('admin.technologies.index'));

    expect(TechnologyCategory::query()->pluck('key')->all())->toBe([$this->category->key])
        ->and(AuditLog::query()->whereIn('operation', ['technology_category.created', 'technology_category.deleted'])->count())->toBe(2);
});

test('deleting a technology moves it to trash', function () {
    $technology = Technology::factory()->create();

    $this->actingAs($this->editor)->delete(route('admin.technologies.destroy', $technology))->assertRedirect(route('admin.technologies.index'));

    expect($technology->fresh()->trashed())->toBeTrue();
});
