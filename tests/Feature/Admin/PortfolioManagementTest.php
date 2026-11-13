<?php

use App\Enums\ContentStatus;
use App\Enums\MediaStatus;
use App\Enums\ProjectType;
use App\Enums\Role;
use App\Enums\TechnologyProficiency;
use App\Models\AuditLog;
use App\Models\Industry;
use App\Models\Media;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\Service;
use App\Models\Technology;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
});

/**
 * @return array<string, mixed>
 */
function projectPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'saas',
        'visibility' => 'public',
        'client_name' => 'Acme Clinics LLC',
        'is_client_public' => '0',
        'project_date' => '2026-03-01',
        'live_url' => 'https://clinic.example.com',
        'ios_url' => 'https://apps.apple.com/app/id123',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'sort_order' => 0,
        'is_featured' => '1',
        'translations' => [
            'en' => ['title' => 'ClinicFlow', 'slug' => 'clinicflow', 'summary' => 'Booking platform.', 'challenge' => 'Phone bookings.', 'solution' => 'Online booking.', 'results' => '- Fewer no-shows'],
            'ar' => ['title' => 'كلينك فلو', 'slug' => 'كلينك-فلو'],
        ],
        'key_facts' => [
            ['translations' => ['en' => ['label' => 'Timeline', 'value' => '14 weeks'], 'ar' => ['label' => 'المدة', 'value' => '14 أسبوعاً']]],
            ['translations' => ['en' => ['label' => 'Platforms', 'value' => 'Web, iOS'], 'ar' => ['label' => '', 'value' => '']]],
            ['translations' => ['en' => ['label' => 'Team', 'value' => '5'], 'ar' => ['label' => 'الفريق', 'value' => '5']]],
            ['translations' => ['en' => ['label' => ''], 'ar' => ['label' => '']]],
        ],
    ], $overrides);
}

test('staff without content permission cannot manage the portfolio', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();
    $project = PortfolioProject::factory()->withTranslation('en')->create();
    $category = PortfolioCategory::factory()->withTranslation('en')->create();

    $this->actingAs($seo)->get(route('admin.portfolio.index'))->assertForbidden();
    $this->actingAs($seo)->get(route('admin.portfolio.create'))->assertForbidden();
    $this->actingAs($seo)->get(route('admin.portfolio.edit', $project))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.portfolio.store'), projectPayload())->assertForbidden();
    $this->actingAs($seo)->put(route('admin.portfolio.update', $project), projectPayload(['lock_version' => 1]))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.portfolio.publish', $project))->assertForbidden();
    $this->actingAs($seo)->delete(route('admin.portfolio.destroy', $project))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.portfolio-categories.store'), ['sort_order' => 0, 'translations' => ['en' => ['name' => 'X', 'slug' => 'x']]])->assertForbidden();
    $this->actingAs($seo)->delete(route('admin.portfolio-categories.destroy', $category))->assertForbidden();
});

test('an editor creates a bilingual project with relations, key facts and an ordered gallery', function () {
    $category = PortfolioCategory::factory()->withTranslation('en')->create();
    $industry = Industry::factory()->withTranslation('en')->create();
    $technology = Technology::factory()->withTranslation('en')->create();
    $service = Service::factory()->withTranslation('en')->create();
    [$first, $second, $third] = Media::factory()->count(3)->create()->all();

    $response = $this->actingAs($this->editor)->post(route('admin.portfolio.store'), projectPayload([
        'portfolio_category_id' => $category->id,
        'industry_id' => $industry->id,
        'technology_ids' => [$technology->id],
        'service_ids' => [$service->id],
        'cover_media_id' => $first->id,
        'gallery_media_ids' => [$third->id, '', $second->id],
    ]));

    $project = PortfolioProject::query()->with(['translations', 'technologies', 'services', 'gallery', 'cover'])->sole();
    $response->assertRedirect(route('admin.portfolio.edit', $project));

    expect($project->status)->toBe(ContentStatus::Draft)
        ->and($project->type)->toBe(ProjectType::Saas)
        ->and($project->is_featured)->toBeTrue()
        ->and($project->is_client_public)->toBeFalse()
        ->and($project->category->is($category))->toBeTrue()
        ->and($project->industry_id)->toBe($industry->id)
        ->and($project->exactTranslation('ar')->slug)->toBe('كلينك-فلو')
        ->and($project->technologies->pluck('id')->all())->toBe([$technology->id])
        ->and($project->services->pluck('id')->all())->toBe([$service->id])
        ->and($project->coverImage()->id)->toBe($first->id)
        ->and($project->gallery->pluck('id')->all())->toBe([$third->id, $second->id])
        ->and($project->exactTranslation('en')->key_facts)->toHaveCount(3)
        ->and($project->exactTranslation('ar')->key_facts)->toBe([
            ['label' => 'المدة', 'value' => '14 أسبوعاً'],
            null,
            ['label' => 'الفريق', 'value' => '5'],
        ])
        ->and($project->exactTranslation('ar')->filledKeyFacts())->toHaveCount(2)
        ->and(AuditLog::query()->where('operation', 'portfolio_project.created')->where('auditable_type', 'portfolio_project')->exists())->toBeTrue();
});

test('the gallery can be reordered and trimmed', function () {
    [$first, $second, $third] = Media::factory()->count(3)->create()->all();
    $this->actingAs($this->editor)->post(route('admin.portfolio.store'), projectPayload(['gallery_media_ids' => [$first->id, $second->id, $third->id]]));
    $project = PortfolioProject::query()->sole();

    $this->actingAs($this->editor)
        ->put(route('admin.portfolio.update', $project), projectPayload(['lock_version' => 1, 'gallery_media_ids' => [$third->id, $first->id]]))
        ->assertSessionHasNoErrors();

    expect($project->gallery()->pluck('media.id')->all())->toBe([$third->id, $first->id]);
});

test('the index and edit screens render with key facts aligned per language', function () {
    $this->actingAs($this->editor)->post(route('admin.portfolio.store'), projectPayload());
    $project = PortfolioProject::query()->sole();

    $this->actingAs($this->editor)->get(route('admin.portfolio.index'))->assertSuccessful()->assertSee('ClinicFlow');
    $this->actingAs($this->editor)->get(route('admin.portfolio.create'))->assertSuccessful();
    $this->actingAs($this->editor)->get(route('admin.portfolio.edit', $project))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('name="key_facts[2][translations][ar][label]" value="الفريق"', false)
        ->assertSee('name="key_facts[1][translations][en][label]" value="Platforms"', false);
});

test('project input is validated', function (array $overrides, string $field) {
    PortfolioProject::factory()->withTranslation('en', ['slug' => 'taken'])->create();

    $this->actingAs($this->editor)
        ->post(route('admin.portfolio.store'), projectPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate slug' => [['translations' => ['en' => ['slug' => 'taken']]], 'translations.en.slug'],
    'missing default title' => [['translations' => ['en' => ['title' => '']]], 'translations.en.title'],
    'unknown type' => [['type' => 'blockchain'], 'type'],
    'unknown visibility' => [['visibility' => 'secret'], 'visibility'],
    'http live url' => [['live_url' => 'http://clinic.example.com'], 'live_url'],
    'javascript url' => [['github_url' => 'javascript:alert(1)'], 'github_url'],
    'not a url' => [['android_url' => 'play store'], 'android_url'],
    'non video host' => [['video_url' => 'https://example.com/video.mp4'], 'video_url'],
    'video without id' => [['video_url' => 'https://www.youtube.com/'], 'video_url'],
    'unknown industry' => [['industry_id' => 999], 'industry_id'],
    'unknown technology' => [['technology_ids' => [999]], 'technology_ids.0'],
    'non-numeric gallery item' => [['gallery_media_ids' => ['abc']], 'gallery_media_ids.0'],
]);

test('only ready library images can join the gallery', function () {
    $processing = Media::factory()->create(['status' => MediaStatus::Processing]);
    $pdf = Media::factory()->create(['mime_type' => 'application/pdf']);

    $this->actingAs($this->editor)->post(route('admin.portfolio.store'), projectPayload(['gallery_media_ids' => [$processing->id]]))->assertSessionHasErrors('gallery_media_ids.0');
    $this->actingAs($this->editor)->post(route('admin.portfolio.store'), projectPayload(['gallery_media_ids' => [$pdf->id]]))->assertSessionHasErrors('gallery_media_ids.0');
});

test('youtube and vimeo links are accepted and embedded privately', function (string $url, string $embed) {
    expect(PortfolioProject::isAllowedVideoUrl($url))->toBeTrue()
        ->and(PortfolioProject::embedUrlFor($url))->toBe($embed);
})->with([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871?dnt=1'],
]);

test('saving an outdated project version is rejected', function () {
    $this->actingAs($this->editor)->post(route('admin.portfolio.store'), projectPayload());
    $project = PortfolioProject::query()->sole();

    $this->actingAs($this->editor)
        ->put(route('admin.portfolio.update', $project), projectPayload(['lock_version' => 1, 'translations' => ['en' => ['title' => 'First edit']]]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->editor)
        ->put(route('admin.portfolio.update', $project), projectPayload(['lock_version' => 1, 'translations' => ['en' => ['title' => 'Second edit']]]))
        ->assertSessionHasErrors('lock_version');

    expect($project->fresh()->load('translations')->exactTranslation('en')->title)->toBe('First edit')
        ->and($project->fresh()->lock_version)->toBe(2);
});

test('publishing requires default-language content and is audited', function () {
    $arabicOnly = PortfolioProject::factory()->draft()->withTranslation('ar')->create();
    $project = PortfolioProject::factory()->draft()->withTranslation('en')->create();

    $this->actingAs($this->editor)->post(route('admin.portfolio.publish', $arabicOnly))->assertSessionHasErrors('status');
    $this->actingAs($this->editor)->post(route('admin.portfolio.publish', $project))->assertSessionHasNoErrors();

    expect($project->fresh()->status)->toBe(ContentStatus::Published)
        ->and(AuditLog::query()->where('operation', 'portfolio_project.published')->count())->toBe(1);
});

test('deleting moves a project to trash', function () {
    $project = PortfolioProject::factory()->withTranslation('en')->create();

    $this->actingAs($this->editor)->delete(route('admin.portfolio.destroy', $project))->assertRedirect(route('admin.portfolio.index'));

    expect($project->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'portfolio_project.deleted')->count())->toBe(1);
});

test('portfolio categories are created with unique slugs and cannot be deleted while in use', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.portfolio-categories.store'), ['sort_order' => 1, 'translations' => ['en' => ['name' => 'SaaS', 'slug' => 'saas'], 'ar' => ['name' => 'برمجيات', 'slug' => 'برمجيات']]])
        ->assertRedirect(route('admin.portfolio.index'));

    $category = PortfolioCategory::query()->with('translations')->sole();
    expect($category->exactTranslation('ar')->name)->toBe('برمجيات')
        ->and(AuditLog::query()->where('operation', 'portfolio_category.created')->exists())->toBeTrue();

    $this->actingAs($this->editor)
        ->post(route('admin.portfolio-categories.store'), ['sort_order' => 1, 'translations' => ['en' => ['name' => 'Again', 'slug' => 'saas']]])
        ->assertSessionHasErrors('translations.en.slug');

    $this->actingAs($this->editor)->get(route('admin.portfolio-categories.edit', $category))->assertSuccessful();

    PortfolioProject::factory()->create(['portfolio_category_id' => $category->id]);
    $this->actingAs($this->editor)->delete(route('admin.portfolio-categories.destroy', $category))->assertSessionHasErrors('category');

    $empty = PortfolioCategory::factory()->withTranslation('en')->create();
    $this->actingAs($this->editor)->delete(route('admin.portfolio-categories.destroy', $empty))->assertRedirect(route('admin.portfolio.index'));
    expect(PortfolioCategory::query()->whereKey($empty->id)->exists())->toBeFalse();
});

test('a technology is verified only while a published public project uses it', function () {
    $technology = Technology::factory()->expertise()->withTranslation('en')->create();
    $project = PortfolioProject::factory()->draft()->withTranslation('en')->create();
    $project->technologies()->attach($technology);

    $proficiency = fn (): TechnologyProficiency => Technology::query()->withProficiency()->findOrFail($technology->id)->proficiency();

    expect($proficiency())->toBe(TechnologyProficiency::Expertise);

    $this->actingAs($this->editor)->post(route('admin.portfolio.publish', $project));
    expect($proficiency())->toBe(TechnologyProficiency::VerifiedInProjects)
        ->and($technology->fresh()->proficiency())->toBe(TechnologyProficiency::VerifiedInProjects);

    $project->update(['visibility' => 'unlisted']);
    expect($proficiency())->toBe(TechnologyProficiency::Expertise);

    $project->update(['visibility' => 'public']);
    expect($proficiency())->toBe(TechnologyProficiency::VerifiedInProjects);

    $this->actingAs($this->editor)->post(route('admin.portfolio.publish', $project));
    expect($proficiency())->toBe(TechnologyProficiency::Expertise);

    $this->actingAs($this->editor)->post(route('admin.portfolio.publish', $project));
    $this->actingAs($this->editor)->delete(route('admin.portfolio.destroy', $project));
    expect($proficiency())->toBe(TechnologyProficiency::Expertise);
});
