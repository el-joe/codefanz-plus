<?php

use App\Actions\Governance\ApplyApprovedChange;
use App\Enums\ChangeRequestStatus;
use App\Enums\ContentStatus;
use App\Enums\MediaStatus;
use App\Enums\Role;
use App\Enums\ServiceTier;
use App\Exceptions\GovernanceException;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\Media;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\Technology;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
});

/**
 * @return array<string, mixed>
 */
function servicePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'tier' => 'core',
        'icon' => 'globe',
        'sort_order' => 1,
        'is_featured' => '1',
        'translations' => [
            'en' => ['title' => 'Web Development', 'slug' => 'web-development', 'summary' => 'Corporate sites and portals.', 'body' => '## Overview'],
            'ar' => ['title' => 'تطوير المواقع', 'slug' => 'تطوير-المواقع', 'summary' => 'مواقع الشركات والبوابات.'],
        ],
        'features' => [
            ['translations' => ['en' => ['title' => 'Discovery workshop'], 'ar' => ['title' => 'ورشة استكشاف']]],
            ['translations' => ['en' => ['title' => 'Launch support', 'description' => 'Two weeks of care.']]],
            ['translations' => ['en' => ['title' => ''], 'ar' => ['title' => '']]],
        ],
        'faqs' => [
            ['translations' => ['en' => ['question' => 'How long does it take?', 'answer' => 'Usually 6–10 weeks.']]],
        ],
    ], $overrides);
}

test('staff without content permission cannot manage services', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();
    $service = Service::factory()->withTranslation('en')->create();

    $this->actingAs($seo)->get(route('admin.services.index'))->assertForbidden();
    $this->actingAs($seo)->get(route('admin.services.edit', $service))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.services.store'), servicePayload())->assertForbidden();
    $this->actingAs($seo)->post(route('admin.services.publish', $service))->assertForbidden();
    $this->actingAs($seo)->delete(route('admin.services.destroy', $service))->assertForbidden();
});

test('an editor creates a bilingual service with features, faqs, stack and cover', function () {
    $technology = Technology::factory()->withTranslation('en', ['name' => 'Laravel'])->create();
    $cover = Media::factory()->create();

    $response = $this->actingAs($this->editor)->post(route('admin.services.store'), servicePayload([
        'technology_ids' => [$technology->id],
        'cover_media_id' => $cover->id,
    ]));

    $service = Service::query()->with(['translations', 'features.translations', 'faqs.translations', 'technologies', 'cover'])->sole();

    $response->assertRedirect(route('admin.services.edit', $service));

    expect($service->status)->toBe(ContentStatus::Draft)
        ->and($service->tier)->toBe(ServiceTier::Core)
        ->and($service->is_featured)->toBeTrue()
        ->and($service->exactTranslation('ar')->slug)->toBe('تطوير-المواقع')
        ->and($service->features)->toHaveCount(2)
        ->and($service->features->first()->exactTranslation('ar')->title)->toBe('ورشة استكشاف')
        ->and($service->features->last()->exactTranslation('ar'))->toBeNull()
        ->and($service->faqs)->toHaveCount(1)
        ->and($service->technologies->pluck('id')->all())->toBe([$technology->id])
        ->and($service->coverImage()->id)->toBe($cover->id)
        ->and(AuditLog::query()->where('operation', 'service.created')->where('auditable_type', 'service')->exists())->toBeTrue();
});

test('the index and edit screens render with one card per language', function () {
    $service = Service::factory()->withTranslation('en', ['title' => 'Cloud & DevOps'])->create();
    ServiceFeature::factory()->for($service)->withTranslation('en', ['title' => 'CI/CD pipelines'])->create();

    $this->actingAs($this->editor)->get(route('admin.services.index'))->assertSuccessful()->assertSee('Cloud &amp; DevOps', false);
    $this->actingAs($this->editor)->get(route('admin.services.create'))->assertSuccessful();
    $this->actingAs($this->editor)->get(route('admin.services.edit', $service))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('CI/CD pipelines');
});

test('updating replaces, reorders and removes child rows', function () {
    $this->actingAs($this->editor)->post(route('admin.services.store'), servicePayload());
    $service = Service::query()->with('features')->sole();
    [$first, $second] = $service->features->all();

    $this->actingAs($this->editor)->put(route('admin.services.update', $service), [
        ...servicePayload(),
        'lock_version' => $service->lock_version,
        'features' => [
            ['id' => $second->id, 'translations' => ['en' => ['title' => 'Launch support (updated)']]],
            ['translations' => ['en' => ['title' => 'Brand new feature']]],
        ],
        'faqs' => [],
    ])->assertSessionHasNoErrors();

    $features = $service->features()->with('translations')->get();

    expect($features->pluck('id')->first())->toBe($second->id)
        ->and($features->first()->exactTranslation('en')->title)->toBe('Launch support (updated)')
        ->and($features->last()->exactTranslation('en')->title)->toBe('Brand new feature')
        ->and(ServiceFeature::query()->whereKey($first->id)->exists())->toBeFalse()
        ->and($service->faqs()->count())->toBe(0);
});

test('child rows of another service cannot be hijacked', function () {
    $foreign = ServiceFeature::factory()->withTranslation('en')->create();
    $this->actingAs($this->editor)->post(route('admin.services.store'), servicePayload());
    $service = Service::query()->latest('id')->first();

    $this->actingAs($this->editor)
        ->put(route('admin.services.update', $service), [...servicePayload(), 'lock_version' => 1, 'features' => [['id' => $foreign->id, 'translations' => ['en' => ['title' => 'Mine now']]]]])
        ->assertSessionHasErrors('features.0.id');
});

test('service input is validated', function (array $overrides, string $field) {
    Service::factory()->withTranslation('en', ['slug' => 'taken'])->create();

    $this->actingAs($this->editor)
        ->post(route('admin.services.store'), servicePayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate slug' => [['translations' => ['en' => ['slug' => 'taken']]], 'translations.en.slug'],
    'malformed slug' => [['translations' => ['en' => ['slug' => 'web development']]], 'translations.en.slug'],
    'missing default title' => [['translations' => ['en' => ['title' => '']]], 'translations.en.title'],
    'arabic title without slug' => [['translations' => ['ar' => ['slug' => '']]], 'translations.ar.slug'],
    'unknown tier' => [['tier' => 'premium'], 'tier'],
    'unknown icon' => [['icon' => 'skull'], 'icon'],
    'unknown technology' => [['technology_ids' => [999]], 'technology_ids.0'],
]);

test('a slug used in another language is allowed', function () {
    Service::factory()->withTranslation('ar', ['slug' => 'web-development'])->create();

    $this->actingAs($this->editor)->post(route('admin.services.store'), servicePayload())->assertSessionHasNoErrors();
});

test('only ready library images can be used as a cover', function () {
    $processing = Media::factory()->create(['status' => MediaStatus::Processing]);
    $pdf = Media::factory()->create(['mime_type' => 'application/pdf']);

    $this->actingAs($this->editor)->post(route('admin.services.store'), servicePayload(['cover_media_id' => $processing->id]))->assertSessionHasErrors('cover_media_id');
    $this->actingAs($this->editor)->post(route('admin.services.store'), servicePayload(['cover_media_id' => $pdf->id]))->assertSessionHasErrors('cover_media_id');
});

test('sub-services nest one level deep only', function () {
    $parent = Service::factory()->withTranslation('en')->create();
    $child = Service::factory()->withTranslation('en')->create(['parent_id' => $parent->id]);

    $this->actingAs($this->editor)
        ->post(route('admin.services.store'), servicePayload(['parent_id' => $child->id]))
        ->assertSessionHasErrors('parent_id');

    $this->actingAs($this->editor)
        ->put(route('admin.services.update', $parent), servicePayload(['lock_version' => 1, 'parent_id' => Service::factory()->create()->id, 'translations' => ['en' => ['slug' => $parent->translations()->value('slug')]]]))
        ->assertSessionHasErrors('parent_id');

    $this->actingAs($this->editor)
        ->post(route('admin.services.store'), servicePayload(['parent_id' => $parent->id]))
        ->assertSessionHasNoErrors();
});

test('saving an outdated version is rejected', function () {
    $this->actingAs($this->editor)->post(route('admin.services.store'), servicePayload());
    $service = Service::query()->sole();

    $this->actingAs($this->editor)
        ->put(route('admin.services.update', $service), servicePayload(['lock_version' => 1, 'translations' => ['en' => ['title' => 'First edit']]]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->editor)
        ->put(route('admin.services.update', $service), servicePayload(['lock_version' => 1, 'translations' => ['en' => ['title' => 'Second edit']]]))
        ->assertSessionHasErrors('lock_version');

    expect($service->fresh()->load('translations')->exactTranslation('en')->title)->toBe('First edit')
        ->and($service->fresh()->lock_version)->toBe(2);
});

test('a manual edit makes pending AI proposals for the service stale', function () {
    $service = Service::factory()->withTranslation('en', ['slug' => 'web-development'])->create();
    $changeRequest = ChangeRequest::factory()->create([
        'target_type' => 'service',
        'target_id' => $service->id,
        'target_version' => 1,
        'status' => ChangeRequestStatus::Approved,
    ]);

    $this->actingAs($this->editor)->get(route('admin.services.edit', $service))->assertSee($changeRequest->reference);
    $this->actingAs($this->editor)->put(route('admin.services.update', $service), servicePayload(['lock_version' => 1]));

    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();

    expect(fn () => app(ApplyApprovedChange::class)->handle($changeRequest, $admin))->toThrow(GovernanceException::class);
});

test('publishing requires default-language content and is audited', function () {
    $arabicOnly = Service::factory()->draft()->withTranslation('ar')->create();
    $service = Service::factory()->draft()->withTranslation('en')->create();

    $this->actingAs($this->editor)->post(route('admin.services.publish', $arabicOnly))->assertSessionHasErrors('status');

    $this->actingAs($this->editor)->post(route('admin.services.publish', $service))->assertSessionHasNoErrors();
    expect($service->fresh()->status)->toBe(ContentStatus::Published)->and($service->fresh()->published_at)->not->toBeNull();

    $this->actingAs($this->editor)->post(route('admin.services.publish', $service));
    expect($service->fresh()->status)->toBe(ContentStatus::Draft)
        ->and(AuditLog::query()->whereIn('operation', ['service.published', 'service.unpublished'])->count())->toBe(2);
});

test('deleting moves a service to trash unless it has sub-services', function () {
    $parent = Service::factory()->withTranslation('en')->create();
    Service::factory()->create(['parent_id' => $parent->id]);
    $service = Service::factory()->withTranslation('en')->create();

    $this->actingAs($this->editor)->delete(route('admin.services.destroy', $parent))->assertSessionHasErrors('status');
    $this->actingAs($this->editor)->delete(route('admin.services.destroy', $service))->assertRedirect(route('admin.services.index'));

    expect($parent->fresh()->trashed())->toBeFalse()
        ->and($service->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'service.deleted')->count())->toBe(1);
});
