<?php

use App\Actions\Governance\ApplyApprovedChange;
use App\Enums\ChangeRequestStatus;
use App\Enums\PageStatus;
use App\Enums\Role;
use App\Exceptions\GovernanceException;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\Page;
use App\Models\User;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
});

/**
 * @return array<string, mixed>
 */
function pagePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'template' => 'legal',
        'translations' => [
            'en' => ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'body' => '## Data we collect', 'seo_title' => 'Privacy', 'seo_description' => 'How we handle data.'],
            'ar' => ['title' => 'سياسة الخصوصية', 'slug' => 'سياسة-الخصوصية', 'body' => '## البيانات التي نجمعها'],
        ],
    ], $overrides);
}

test('staff without content permission cannot manage pages', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();

    $this->actingAs($seo)->get(route('admin.pages.index'))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.pages.store'), pagePayload())->assertForbidden();
});

test('an editor creates a bilingual page as a draft', function () {
    $response = $this->actingAs($this->editor)->post(route('admin.pages.store'), pagePayload());

    $page = Page::query()->with('translations')->sole();

    $response->assertRedirect(route('admin.pages.edit', $page));

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->template)->toBe('legal')
        ->and($page->translation('ar')->slug)->toBe('سياسة-الخصوصية')
        ->and(AuditLog::query()->where('operation', 'page.created')->exists())->toBeTrue();
});

test('the index and edit screens render', function () {
    $page = Page::factory()->withTranslation('en', ['title' => 'About us'])->create();

    $this->actingAs($this->editor)->get(route('admin.pages.index'))->assertSuccessful()->assertSee('About us');
    $this->actingAs($this->editor)->get(route('admin.pages.create'))->assertSuccessful();
    $this->actingAs($this->editor)->get(route('admin.pages.edit', $page))->assertSuccessful()->assertSee('dir="rtl"', false);
});

test('slugs must be well formed and unique per language', function (array $translation, string $field) {
    Page::factory()->withTranslation('en', ['slug' => 'terms'])->create();

    $this->actingAs($this->editor)
        ->post(route('admin.pages.store'), pagePayload(['translations' => ['en' => $translation]]))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate' => [['slug' => 'terms'], 'translations.en.slug'],
    'spaces' => [['slug' => 'privacy policy'], 'translations.en.slug'],
    'double hyphen' => [['slug' => 'privacy--policy'], 'translations.en.slug'],
    'path traversal' => [['slug' => '../admin'], 'translations.en.slug'],
    'missing default title' => [['title' => ''], 'translations.en.title'],
]);

test('a slug used in another language is allowed', function () {
    Page::factory()->withTranslation('ar', ['slug' => 'privacy-policy'])->create();

    $this->actingAs($this->editor)
        ->post(route('admin.pages.store'), pagePayload())
        ->assertSessionHasNoErrors();
});

test('saving an outdated version is rejected', function () {
    $page = Page::factory()->withTranslation('en', ['title' => 'Terms', 'slug' => 'terms'])->create();

    $this->actingAs($this->editor)
        ->put(route('admin.pages.update', $page), pagePayload(['lock_version' => 1, 'translations' => ['en' => ['title' => 'First edit', 'slug' => 'terms']]]))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->editor)
        ->put(route('admin.pages.update', $page), pagePayload(['lock_version' => 1, 'translations' => ['en' => ['title' => 'Second edit', 'slug' => 'terms']]]))
        ->assertSessionHasErrors('lock_version');

    expect($page->fresh()->translation('en')->title)->toBe('First edit')
        ->and($page->fresh()->lock_version)->toBe(2);
});

test('clearing an optional language removes its translation', function () {
    $this->actingAs($this->editor)->post(route('admin.pages.store'), pagePayload());
    $page = Page::query()->sole();

    $this->actingAs($this->editor)
        ->put(route('admin.pages.update', $page), pagePayload(['lock_version' => $page->lock_version, 'translations' => ['ar' => ['title' => '', 'slug' => '', 'body' => '']]]))
        ->assertSessionHasNoErrors();

    expect($page->translations()->pluck('locale')->all())->toBe(['en']);
});

test('a manual edit makes pending AI proposals stale', function () {
    $page = Page::factory()->withTranslation('en', ['slug' => 'terms'])->create();
    $changeRequest = ChangeRequest::factory()->create(['target_id' => $page->id, 'status' => ChangeRequestStatus::Approved]);

    $this->actingAs($this->editor)
        ->get(route('admin.pages.edit', $page))
        ->assertSee($changeRequest->reference);

    $this->actingAs($this->editor)
        ->put(route('admin.pages.update', $page), pagePayload(['lock_version' => 1, 'translations' => ['en' => ['slug' => 'terms']]]));

    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();

    expect(fn () => app(ApplyApprovedChange::class)->handle($changeRequest, $admin))
        ->toThrow(GovernanceException::class);
});

test('publishing and unpublishing a page', function () {
    $page = Page::factory()->withTranslation('en')->create(['status' => PageStatus::Draft, 'published_at' => null]);

    $this->actingAs($this->editor)->post(route('admin.pages.publish', $page))->assertSessionHasNoErrors();
    expect($page->fresh()->status)->toBe(PageStatus::Published)->and($page->fresh()->published_at)->not->toBeNull();

    $this->actingAs($this->editor)->post(route('admin.pages.publish', $page));
    expect($page->fresh()->status)->toBe(PageStatus::Draft);

    expect(AuditLog::query()->whereIn('operation', ['page.published', 'page.unpublished'])->count())->toBe(2);
});

test('a page without default-language content cannot be published', function () {
    $page = Page::factory()->withTranslation('ar')->create(['status' => PageStatus::Draft]);

    $this->actingAs($this->editor)
        ->post(route('admin.pages.publish', $page))
        ->assertSessionHasErrors('status');

    expect($page->fresh()->status)->toBe(PageStatus::Draft);
});

test('deleting a page moves it to trash', function () {
    $page = Page::factory()->withTranslation('en')->create();

    $this->actingAs($this->editor)
        ->delete(route('admin.pages.destroy', $page))
        ->assertRedirect(route('admin.pages.index'));

    expect($page->fresh()->trashed())->toBeTrue();
});
