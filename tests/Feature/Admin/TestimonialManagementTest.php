<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\PortfolioProject;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->create();
});

/**
 * @return array<string, mixed>
 */
function testimonialPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'author_name' => 'Rana Hassan',
        'company' => 'Acme Clinics',
        'rating' => 5,
        'consent_confirmed' => '1',
        'is_featured' => '1',
        'sort_order' => 0,
        'translations' => [
            'en' => ['quote' => 'They shipped on time.', 'author_role' => 'Operations Director'],
            'ar' => ['quote' => 'سلّموا في الموعد.', 'author_role' => 'مديرة العمليات'],
        ],
    ], $overrides);
}

test('staff without content permission cannot manage testimonials', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->create();
    $testimonial = Testimonial::factory()->withTranslation()->create();

    $this->actingAs($seo)->get(route('admin.testimonials.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.testimonials.store'), testimonialPayload())->assertForbidden();
    $this->actingAs($seo)->post(route('admin.testimonials.publish', $testimonial))->assertForbidden();
});

test('an editor creates a bilingual testimonial linked to a project with consent recorded', function () {
    $project = PortfolioProject::factory()->withTranslation('en', ['title' => 'ClinicFlow'])->create();

    $this->actingAs($this->editor)
        ->post(route('admin.testimonials.store'), testimonialPayload(['portfolio_project_id' => $project->id]))
        ->assertSessionHasNoErrors();

    $testimonial = Testimonial::query()->with('translations')->sole();

    expect($testimonial->status)->toBe(ContentStatus::Draft)
        ->and($testimonial->consent_confirmed)->toBeTrue()
        ->and($testimonial->consent_confirmed_by)->toBe($this->editor->id)
        ->and($testimonial->consent_confirmed_at)->not->toBeNull()
        ->and($testimonial->portfolio_project_id)->toBe($project->id)
        ->and($testimonial->exactTranslation('ar')->quote)->toBe('سلّموا في الموعد.')
        ->and(AuditLog::query()->where('operation', 'testimonial.created')->exists())->toBeTrue();

    $this->actingAs($this->editor)->get(route('admin.testimonials.index'))->assertSuccessful()->assertSee('Rana Hassan');
    $this->actingAs($this->editor)->get(route('admin.testimonials.edit', $testimonial))->assertSuccessful()->assertSee('They shipped on time.');
});

test('testimonial input is validated', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.testimonials.store'), testimonialPayload(['author_name' => '', 'rating' => 6, 'translations' => ['en' => ['quote' => '']]]))
        ->assertSessionHasErrors(['author_name', 'rating', 'translations.en.quote']);

    expect(Testimonial::query()->count())->toBe(0);
});

test('a testimonial cannot be published without confirmed consent', function () {
    $this->actingAs($this->editor)->post(route('admin.testimonials.store'), testimonialPayload(['consent_confirmed' => '0']));
    $testimonial = Testimonial::query()->sole();

    $this->actingAs($this->editor)->post(route('admin.testimonials.publish', $testimonial))->assertSessionHasErrors('status');

    expect($testimonial->fresh()->status)->toBe(ContentStatus::Draft);
});

test('withdrawing consent unpublishes a testimonial and hides it from visitors', function () {
    $this->actingAs($this->editor)->post(route('admin.testimonials.store'), testimonialPayload());
    $testimonial = Testimonial::query()->sole();
    $this->actingAs($this->editor)->post(route('admin.testimonials.publish', $testimonial))->assertSessionHasNoErrors();

    $this->get('/en/testimonials')->assertSee('They shipped on time.');

    $this->actingAs($this->editor)
        ->put(route('admin.testimonials.update', $testimonial), testimonialPayload(['consent_confirmed' => '0']))
        ->assertSessionHasNoErrors();

    $testimonial->refresh();
    expect($testimonial->status)->toBe(ContentStatus::Draft)
        ->and($testimonial->consent_confirmed)->toBeFalse()
        ->and($testimonial->consent_confirmed_at)->toBeNull();

    $this->get('/en/testimonials')->assertDontSee('They shipped on time.');
});

test('deleting a testimonial moves it to trash and is audited', function () {
    $testimonial = Testimonial::factory()->withTranslation()->create();

    $this->actingAs($this->editor)->delete(route('admin.testimonials.destroy', $testimonial))->assertRedirect(route('admin.testimonials.index'));

    expect($testimonial->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'testimonial.deleted')->exists())->toBeTrue();
});
