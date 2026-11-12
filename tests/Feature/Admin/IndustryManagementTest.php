<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Industry;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->create();
});

/**
 * @return array<string, mixed>
 */
function industryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'icon' => 'heart-pulse',
        'sort_order' => 0,
        'translations' => [
            'en' => ['name' => 'Healthcare', 'slug' => 'healthcare', 'summary' => 'Booking and patient records.', 'typical_solutions' => "Clinic management\n Patient apps \n\nClinic management"],
            'ar' => ['name' => 'الرعاية الصحية', 'slug' => 'الرعاية-الصحية', 'typical_solutions' => 'إدارة العيادات'],
        ],
    ], $overrides);
}

test('staff without content permission cannot manage industries', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->create();

    $this->actingAs($seo)->get(route('admin.industries.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.industries.store'), industryPayload())->assertForbidden();
});

test('an editor creates a bilingual industry with typical solutions', function () {
    $this->actingAs($this->editor)->post(route('admin.industries.store'), industryPayload())->assertSessionHasNoErrors();

    $industry = Industry::query()->with('translations')->sole();

    expect($industry->status)->toBe(ContentStatus::Draft)
        ->and($industry->exactTranslation('en')->typical_solutions)->toBe(['Clinic management', 'Patient apps'])
        ->and($industry->exactTranslation('ar')->typical_solutions)->toBe(['إدارة العيادات'])
        ->and(AuditLog::query()->where('operation', 'industry.created')->exists())->toBeTrue();

    $this->actingAs($this->editor)->get(route('admin.industries.index'))->assertSuccessful()->assertSee('Healthcare');
    $this->actingAs($this->editor)->get(route('admin.industries.edit', $industry))->assertSuccessful()->assertSee('Patient apps');
});

test('industry slugs are unique per language', function () {
    Industry::factory()->withTranslation('en', ['slug' => 'healthcare'])->create();

    $this->actingAs($this->editor)->post(route('admin.industries.store'), industryPayload())->assertSessionHasErrors('translations.en.slug');
    $this->actingAs($this->editor)->post(route('admin.industries.store'), industryPayload(['translations' => ['en' => ['slug' => 'health']]]))->assertSessionHasNoErrors();
});

test('publishing, updating and deleting an industry', function () {
    $this->actingAs($this->editor)->post(route('admin.industries.store'), industryPayload());
    $industry = Industry::query()->sole();

    $this->actingAs($this->editor)->post(route('admin.industries.publish', $industry))->assertSessionHasNoErrors();
    expect($industry->fresh()->status)->toBe(ContentStatus::Published);

    $this->actingAs($this->editor)
        ->put(route('admin.industries.update', $industry), industryPayload(['translations' => ['ar' => ['name' => '', 'slug' => '']]]))
        ->assertSessionHasNoErrors();
    expect($industry->translations()->pluck('locale')->all())->toBe(['en']);

    $this->actingAs($this->editor)->delete(route('admin.industries.destroy', $industry))->assertRedirect(route('admin.industries.index'));
    expect($industry->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->whereIn('operation', ['industry.published', 'industry.updated', 'industry.deleted'])->count())->toBe(3);
});
