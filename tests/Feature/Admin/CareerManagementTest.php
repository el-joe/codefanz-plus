<?php

use App\Enums\ContentStatus;
use App\Enums\EmploymentType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Career;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->create();
});

/**
 * @return array<string, mixed>
 */
function careerPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'employment_type' => 'full_time',
        'is_remote' => '1',
        'closes_at' => now()->addMonth()->toDateString(),
        'sort_order' => 0,
        'translations' => [
            'en' => [
                'title' => 'Senior Laravel Developer', 'slug' => 'Senior-Laravel-Developer', 'department' => 'Engineering', 'location' => 'Cairo',
                'summary' => 'Build APIs.', 'responsibilities' => "- Build APIs\n\n• Review code", 'requirements' => "5 years PHP\n", 'body' => 'About the role.',
            ],
            'ar' => ['title' => 'مطور Laravel أول', 'slug' => 'مطور-laravel-أول', 'responsibilities' => 'بناء الواجهات'],
        ],
    ], $overrides);
}

test('staff without content permission cannot manage careers', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->create();

    $this->actingAs($seo)->get(route('admin.careers.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.careers.store'), careerPayload())->assertForbidden();
});

test('an editor creates a bilingual position with line lists', function () {
    $this->actingAs($this->editor)->post(route('admin.careers.store'), careerPayload())->assertSessionHasNoErrors();

    $career = Career::query()->with('translations')->sole();
    $english = $career->exactTranslation('en');

    expect($career->status)->toBe(ContentStatus::Draft)
        ->and($career->employment_type)->toBe(EmploymentType::FullTime)
        ->and($career->is_remote)->toBeTrue()
        ->and($english->slug)->toBe('senior-laravel-developer')
        ->and($english->responsibilities)->toBe(['Build APIs', 'Review code'])
        ->and($english->requirements)->toBe(['5 years PHP'])
        ->and($career->exactTranslation('ar')->responsibilities)->toBe(['بناء الواجهات'])
        ->and(AuditLog::query()->where('operation', 'career.created')->exists())->toBeTrue();

    $this->actingAs($this->editor)->get(route('admin.careers.edit', $career))->assertSuccessful()->assertSee('Review code');
});

test('career input is validated', function () {
    Career::factory()->withTranslation('en', ['slug' => 'taken'])->create();

    $this->actingAs($this->editor)
        ->post(route('admin.careers.store'), careerPayload(['employment_type' => 'gig', 'translations' => ['en' => ['slug' => 'taken']]]))
        ->assertSessionHasErrors(['employment_type', 'translations.en.slug']);
});

test('publishing and trashing a position', function () {
    $this->actingAs($this->editor)->post(route('admin.careers.store'), careerPayload());
    $career = Career::query()->sole();

    $this->actingAs($this->editor)->post(route('admin.careers.publish', $career))->assertSessionHasNoErrors();
    expect($career->fresh()->status)->toBe(ContentStatus::Published);
    $this->get('/en/careers/senior-laravel-developer')->assertSuccessful();

    $this->actingAs($this->editor)->delete(route('admin.careers.destroy', $career))->assertRedirect(route('admin.careers.index'));
    expect($career->fresh()->trashed())->toBeTrue();
    $this->get('/en/careers/senior-laravel-developer')->assertNotFound();
});
