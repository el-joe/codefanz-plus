<?php

use App\Actions\Governance\ApplyApprovedChange;
use App\Actions\Governance\ProposeChange;
use App\Actions\Governance\ReviewChangeRequest;
use App\Enums\ChangeOrigin;
use App\Enums\PostOrigin;
use App\Enums\PostStatus;
use App\Enums\Role;
use App\Exceptions\GovernanceException;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostChecklistItem;
use App\Models\PostRevision;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\PostReviewed;
use App\Notifications\PostSubmittedForReview;
use App\Services\Blog\EditorialChecklist;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->editor = User::factory()->role(Role::ContentManager)->create();
    $this->reviewer = User::factory()->role(Role::ContentManager)->create();
});

/**
 * @return array<string, mixed>
 */
function postPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'translations' => [
            'en' => [
                'title' => 'How to scope an AI feature without overspending',
                'slug' => 'scope-ai-feature',
                'excerpt' => 'A practical checklist for founders.',
                'body' => '<p>Scope an AI feature with clear goals.</p><h2>Start with the decision</h2><p>See <a href="/en/services">services</a>.</p><script>alert(1)</script>',
                'focus_keyword' => 'AI feature',
                'seo_description' => 'A practical checklist for founders: data readiness, evaluation, cost controls and human review.',
            ],
            'ar' => ['title' => '', 'slug' => ''],
        ],
    ], $overrides);
}

function bilingualPost(array $state = []): Post
{
    return Post::factory()->withTranslation('en', ['title' => 'A practical guide to Laravel queues'])->withTranslation('ar', ['title' => 'دليل عملي لطوابير لارافيل'])->create($state);
}

test('staff without content permission cannot reach the blog admin', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->create();
    $post = bilingualPost(['status' => PostStatus::Draft]);

    $this->actingAs($seo)->get(route('admin.posts.index'))->assertForbidden();
    $this->actingAs($seo)->get(route('admin.posts.edit', $post))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.posts.store'), postPayload())->assertForbidden();
    $this->actingAs($seo)->post(route('admin.posts.submit', $post))->assertForbidden();
    $this->actingAs($seo)->post(route('admin.posts.approve', $post))->assertForbidden();
    $this->actingAs($seo)->get(route('admin.post-categories.index'))->assertForbidden();
});

test('an editor creates a draft whose body is sanitized and versioned', function () {
    $category = PostCategory::factory()->withTranslation('en', ['name' => 'AI & Automation'])->create();
    $tag = Tag::factory()->withTranslation('en', ['name' => 'Laravel'])->create();

    $this->actingAs($this->editor)->post(route('admin.posts.store'), postPayload([
        'post_category_id' => $category->id,
        'tag_ids' => [$tag->id],
        'checklist' => ['accurate' => '1'],
    ]))->assertRedirect();

    $post = Post::query()->with(['translations', 'tags'])->sole();
    $english = $post->exactTranslation('en');

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->origin)->toBe(PostOrigin::Human)
        ->and($post->author_id)->toBe($this->editor->id)
        ->and($post->post_category_id)->toBe($category->id)
        ->and($post->tags->pluck('id')->all())->toBe([$tag->id])
        ->and($post->exactTranslation('ar'))->toBeNull()
        ->and($english->body)->not->toContain('<script')
        ->and($english->body)->toContain('<h2 id="start-with-the-decision">')
        ->and($english->toc)->toHaveCount(1)
        ->and($post->revisions()->count())->toBe(1)
        ->and($post->revisions()->first()->reason)->toBe('created')
        ->and(PostChecklistItem::query()->where('post_id', $post->id)->where('item', 'accurate')->value('is_passed'))->toBeTrue()
        ->and(PostChecklistItem::query()->where('post_id', $post->id)->where('item', 'accurate')->value('checked_by'))->toBe($this->editor->id)
        ->and(AuditLog::query()->where('operation', 'post.created')->exists())->toBeTrue();

    $this->actingAs($this->editor)->get(route('admin.posts.edit', $post))
        ->assertSuccessful()
        ->assertSee('Editorial workflow')
        ->assertSee('data-rich-editor', false)
        ->assertDontSee('data-ai-draft-warning', false);
});

test('slugs are validated, unique per locale and cannot use reserved words', function () {
    bilingualPost();
    $existing = Post::query()->first()->exactTranslation('en')->slug;

    $this->actingAs($this->editor)->post(route('admin.posts.store'), postPayload(['translations' => ['en' => ['slug' => $existing]]]))
        ->assertSessionHasErrors('translations.en.slug');
    $this->actingAs($this->editor)->post(route('admin.posts.store'), postPayload(['translations' => ['en' => ['slug' => 'feed']]]))
        ->assertSessionHasErrors('translations.en.slug');
    $this->actingAs($this->editor)->post(route('admin.posts.store'), postPayload(['translations' => ['en' => ['title' => '']]]))
        ->assertSessionHasErrors('translations.en.title');
    $this->actingAs($this->editor)->post(route('admin.posts.store'), postPayload(['translations' => ['en' => ['canonical_url' => 'javascript:alert(1)']]]))
        ->assertSessionHasErrors('translations.en.canonical_url');
});

test('a stale lock version is rejected', function () {
    $post = bilingualPost(['status' => PostStatus::Draft]);

    $this->actingAs($this->editor)->put(route('admin.posts.update', $post), postPayload(['lock_version' => $post->lock_version]))->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer)->put(route('admin.posts.update', $post), postPayload(['lock_version' => $post->lock_version, 'translations' => ['en' => ['title' => 'Another title that is long enough']]]))
        ->assertSessionHasErrors('lock_version');

    expect($post->fresh()->lock_version)->toBe(2);
});

test('the full editorial workflow: submit, request changes, approve and publish', function () {
    Notification::fake();
    $post = Post::factory()->draft()->withTranslation('en', ['title' => 'Laravel vs Node for your next SaaS'])->create(['author_id' => $this->editor->id]);

    $this->actingAs($this->editor)->post(route('admin.posts.submit', $post))->assertSessionHasNoErrors();
    expect($post->fresh()->status)->toBe(PostStatus::InReview);
    Notification::assertSentTo($this->reviewer, PostSubmittedForReview::class);
    Notification::assertNotSentTo($this->editor, PostSubmittedForReview::class);

    $this->actingAs($this->reviewer)->post(route('admin.posts.request-changes', $post), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($this->reviewer)->post(route('admin.posts.request-changes', $post), ['note' => 'Add sources for the benchmarks.'])->assertSessionHasNoErrors();
    expect($post->fresh())->status->toBe(PostStatus::ChangesRequested)->review_note->toBe('Add sources for the benchmarks.');
    Notification::assertSentTo($this->editor, PostReviewed::class, fn (PostReviewed $notification): bool => ! $notification->approved && $notification->note === 'Add sources for the benchmarks.');

    $this->actingAs($this->editor)->post(route('admin.posts.submit', $post))->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer)->post(route('admin.posts.approve', $post), ['then' => 'none'])->assertSessionHasNoErrors();
    expect($post->fresh())->status->toBe(PostStatus::Approved)->reviewed_by->toBe($this->reviewer->id);
    Notification::assertSentTo($this->editor, PostReviewed::class, fn (PostReviewed $notification): bool => $notification->approved);

    $this->actingAs($this->reviewer)->post(route('admin.posts.publish', $post))->assertSessionHasErrors('acknowledge_warnings');
    $this->actingAs($this->reviewer)->post(route('admin.posts.publish', $post), ['acknowledge_warnings' => '1'])->assertSessionHasNoErrors();

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Published)
        ->and($post->published_at)->not->toBeNull()
        ->and($post->isPubliclyVisible())->toBeTrue()
        ->and(AuditLog::query()->where('auditable_id', $post->id)->where('auditable_type', 'post')->pluck('operation')->all())
        ->toContain('post.submitted', 'post.changes_requested', 'post.approved', 'post.published');

    $this->actingAs($this->reviewer)->post(route('admin.posts.archive', $post))->assertSessionHasNoErrors();
    expect($post->fresh()->status)->toBe(PostStatus::Archived);
});

test('invalid transitions are refused', function () {
    $draft = bilingualPost(['status' => PostStatus::Draft, 'published_at' => null]);

    $this->actingAs($this->reviewer)->post(route('admin.posts.approve', $draft))->assertSessionHasErrors('status');
    $this->actingAs($this->reviewer)->post(route('admin.posts.publish', $draft), ['acknowledge_warnings' => '1'])->assertSessionHasErrors('status');

    expect($draft->fresh()->status)->toBe(PostStatus::Draft);
});

test('submitting requires the default-language title, slug and excerpt', function () {
    $post = Post::factory()->draft()->withTranslation('en', ['excerpt' => null])->create();

    $this->actingAs($this->editor)->post(route('admin.posts.submit', $post))->assertSessionHasErrors('status');
    expect($post->fresh()->status)->toBe(PostStatus::Draft);
});

test('editors without approval permission cannot review', function () {
    $author = User::factory()->create();
    $author->givePermissionTo(['admin.access', 'content.manage']);
    $post = bilingualPost(['status' => PostStatus::InReview, 'published_at' => null]);

    $this->actingAs($author)->post(route('admin.posts.approve', $post))->assertForbidden();
    $this->actingAs($author)->post(route('admin.posts.request-changes', $post), ['note' => 'x'])->assertForbidden();
    $this->actingAs($author)->post(route('admin.posts.archive', $post))->assertForbidden();
});

test('the author of an AI-origin draft cannot approve it, but another reviewer can', function () {
    $post = Post::factory()->aiOrigin()->inReview()->withTranslation('en', ['title' => 'AI drafted article about Laravel'])->create(['author_id' => $this->editor->id]);

    $this->actingAs($this->editor)->get(route('admin.posts.edit', $post))
        ->assertSuccessful()
        ->assertSee('data-ai-draft-warning', false)
        ->assertSee('another reviewer must approve it');
    $this->actingAs($this->editor)->post(route('admin.posts.approve', $post))->assertForbidden();

    $superAdmin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();
    $post->forceFill(['author_id' => $superAdmin->id])->save();
    $this->actingAs($superAdmin)->post(route('admin.posts.approve', $post))->assertForbidden();

    $this->actingAs($this->reviewer)->post(route('admin.posts.approve', $post))->assertSessionHasNoErrors();
    expect($post->fresh()->status)->toBe(PostStatus::Approved);
});

test('approved AI change requests update the text but never publish', function () {
    $post = Post::factory()->draft()->withTranslation('en', ['title' => 'Original title for the article'])->create();

    $changeRequest = app(ProposeChange::class)->handle($post, ['title' => 'Improved title for the article', 'body' => '<h2>New</h2><img src=x onerror=alert(1)>'], ChangeOrigin::Ai, 'Improve title', locale: 'en');
    app(ReviewChangeRequest::class)->approve($changeRequest, $this->reviewer);
    app(ApplyApprovedChange::class)->handle($changeRequest->fresh(), $this->reviewer);

    $post->refresh()->load('translations');
    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->lock_version)->toBe(2)
        ->and($post->exactTranslation('en')->title)->toBe('Improved title for the article')
        ->and($post->exactTranslation('en')->body)->toBe('<h2 id="new">New</h2>')
        ->and($post->revisions()->first()->reason)->toBe('change_request');
});

test('AI proposals cannot change the status of an article', function () {
    $post = Post::factory()->draft()->withTranslation('en')->create();

    app(ProposeChange::class)->handle($post, ['status' => 'published'], ChangeOrigin::Ai, 'Publish', locale: 'en');
})->throws(GovernanceException::class);

test('editing an approved or scheduled article withdraws the approval', function () {
    $post = Post::factory()->scheduled(now()->addDay())->withTranslation('en', ['title' => 'Scheduled article for next week'])->create();

    $this->actingAs($this->editor)->put(route('admin.posts.update', $post), postPayload(['lock_version' => $post->lock_version]))
        ->assertSessionHasNoErrors();

    expect($post->fresh())->status->toBe(PostStatus::Draft)->scheduled_for->toBeNull()->approved_at->toBeNull();
});

test('reviewers schedule approved articles and the scheduler publishes them when due', function () {
    $post = Post::factory()->approved()->withTranslation('en', ['title' => 'Article that goes live tomorrow'])->create();
    $this->reviewer->forceFill(['timezone' => 'Africa/Cairo'])->save();

    $this->actingAs($this->reviewer)->post(route('admin.posts.schedule', $post), ['scheduled_for' => now()->subHour()->timezone('Africa/Cairo')->format('Y-m-d\TH:i'), 'acknowledge_warnings' => '1'])
        ->assertSessionHasErrors('scheduled_for');

    $when = now()->addDay()->startOfMinute();
    $this->actingAs($this->reviewer)->post(route('admin.posts.schedule', $post), ['scheduled_for' => $when->copy()->timezone('Africa/Cairo')->format('Y-m-d\TH:i'), 'acknowledge_warnings' => '1'])
        ->assertSessionHasNoErrors();

    $post->refresh();
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->scheduled_for->equalTo($when))->toBeTrue();

    $this->artisan('posts:publish-scheduled')->assertSuccessful();
    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);

    $this->travelTo($when->copy()->addMinute());
    $this->artisan('posts:publish-scheduled')->expectsOutputToContain('Published 1')->assertSuccessful();

    expect($post->fresh())->status->toBe(PostStatus::Published)
        ->and($post->fresh()->published_at->equalTo($when))->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'post.published')->where('actor_type', 'system')->exists())->toBeTrue();
});

test('the scheduler never publishes articles that were not approved', function () {
    $unapproved = Post::factory()->withTranslation('en')->create(['status' => PostStatus::Scheduled, 'scheduled_for' => now()->subMinute(), 'published_at' => null, 'approved_at' => null]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($unapproved->fresh()->status)->toBe(PostStatus::Scheduled);
});

test('the scheduler command runs every minute', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('posts:publish-scheduled')->assertSuccessful();
});

test('approve and schedule in one step', function () {
    $post = bilingualPost(['status' => PostStatus::InReview, 'published_at' => null]);

    $this->actingAs($this->reviewer)->post(route('admin.posts.approve', $post), ['then' => 'schedule', 'scheduled_for' => now()->addDays(2)->format('Y-m-d\TH:i')])
        ->assertSessionHasErrors('acknowledge_warnings');

    $this->actingAs($this->reviewer)->post(route('admin.posts.approve', $post), ['then' => 'schedule', 'scheduled_for' => now()->addDays(2)->timezone($this->reviewer->fresh()->timezone)->format('Y-m-d\TH:i'), 'acknowledge_warnings' => '1'])
        ->assertRedirect(route('admin.posts.edit', $post));

    expect($post->fresh())->status->toBe(PostStatus::Scheduled)->approved_at->not->toBeNull();
});

test('revisions record each content change and restore creates a new revision', function () {
    $this->actingAs($this->editor)->post(route('admin.posts.store'), postPayload());
    $post = Post::query()->sole();

    $this->actingAs($this->editor)->put(route('admin.posts.update', $post), postPayload([
        'lock_version' => 1,
        'translations' => ['en' => ['title' => 'A rewritten title about AI features', 'body' => '<p>Completely new opening.</p><h2>Start with the decision</h2>']],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($this->editor)->put(route('admin.posts.update', $post), postPayload([
        'lock_version' => 2,
        'translations' => ['en' => ['title' => 'A rewritten title about AI features', 'body' => '<p>Completely new opening.</p><h2>Start with the decision</h2>']],
    ]))->assertSessionHasNoErrors();

    expect($post->revisions()->count())->toBe(2);

    $first = $post->revisions()->get()->last();

    $this->actingAs($this->editor)->get(route('admin.posts.revisions.index', ['post' => $post, 'revision' => $first->id, 'locale' => 'en']))
        ->assertSuccessful()
        ->assertSee('data-diff-row="changed"', false)
        ->assertSee('How to scope an AI feature without overspending')
        ->assertSee('A rewritten title about AI features');

    $this->actingAs($this->editor)->post(route('admin.posts.revisions.restore', [$post, $first]), ['lock_version' => 1])
        ->assertSessionHasErrors('lock_version');

    $this->actingAs($this->editor)->post(route('admin.posts.revisions.restore', [$post, $first]), ['lock_version' => 3])
        ->assertRedirect(route('admin.posts.edit', $post));

    $latest = $post->revisions()->first();
    expect($post->fresh()->translations->firstWhere('locale', 'en')->title)->toBe('How to scope an AI feature without overspending')
        ->and($latest->reason)->toBe('restored')
        ->and($latest->restored_from_id)->toBe($first->id)
        ->and($post->revisions()->count())->toBe(3)
        ->and(AuditLog::query()->where('operation', 'post.revision_restored')->exists())->toBeTrue();

    $other = Post::factory()->create();
    $this->actingAs($this->editor)->post(route('admin.posts.revisions.restore', [$other, $first]), ['lock_version' => 1])->assertNotFound();
});

test('revisions are immutable', function () {
    $this->actingAs($this->editor)->post(route('admin.posts.store'), postPayload());
    $revision = PostRevision::query()->sole();

    $revision->reason = 'tampered';
    $revision->save();

    expect($revision->fresh()->reason)->toBe('created');
});

test('the editorial checklist evaluates each rule', function () {
    $post = Post::factory()->draft()->create();
    $post->translations()->create([
        'locale' => 'en',
        'title' => 'Laravel queues explained for busy founders',
        'slug' => 'laravel-queues',
        'excerpt' => 'Why queues matter.',
        'seo_description' => 'Too short',
        'focus_keyword' => 'laravel queues',
        'body' => '<p>Laravel queues keep your app fast.</p><h2>Why</h2><img src="/storage/a.webp"><a href="https://example.com">x</a>',
    ]);
    $post->load('translations');

    $results = collect(app(EditorialChecklist::class)->evaluate($post, ['accurate' => true]))->keyBy('key');

    expect($results['required_fields']['passed'])->toBeTrue()
        ->and($results['title_length']['passed'])->toBeTrue()
        ->and($results['meta_description']['passed'])->toBeFalse()
        ->and($results['focus_keyword']['passed'])->toBeTrue()
        ->and($results['image_alt']['passed'])->toBeFalse()
        ->and($results['internal_link']['passed'])->toBeFalse()
        ->and($results['heading_structure']['passed'])->toBeTrue()
        ->and($results['languages']['passed'])->toBeFalse()
        ->and($results['languages']['detail'])->toContain('AR')
        ->and($results['accurate']['passed'])->toBeTrue()
        ->and($results['sources_verified']['passed'])->toBeFalse();

    $post->is_single_language = true;
    expect(collect(app(EditorialChecklist::class)->evaluate($post))->firstWhere('key', 'languages')['passed'])->toBeTrue();

    $post->translations->first()->title = 'Short';
    $post->translations->first()->excerpt = null;
    $results = collect(app(EditorialChecklist::class)->evaluate($post))->keyBy('key');
    expect($results['title_length']['passed'])->toBeFalse()
        ->and($results['required_fields']['passed'])->toBeFalse()
        ->and($results['required_fields']['required'])->toBeTrue();
});

test('the live checklist endpoint evaluates the unsaved form', function () {
    $this->actingAs($this->editor)->post(route('admin.posts.checklist'), postPayload(['translations' => ['en' => ['excerpt' => '']]]))
        ->assertSuccessful()
        ->assertSee('data-check="required_fields" data-passed="false"', false)
        ->assertSee('data-check="heading_structure" data-passed="true"', false)
        ->assertSee('data-check="internal_link" data-passed="true"', false);

    $this->actingAs(User::factory()->role(Role::SeoSpecialist)->create())->post(route('admin.posts.checklist'), postPayload())->assertForbidden();
});

test('the posts index filters by status, author and category', function () {
    $category = PostCategory::factory()->withTranslation('en', ['name' => 'Engineering'])->create();
    Post::factory()->withTranslation('en', ['title' => 'Published engineering post'])->create(['post_category_id' => $category->id, 'author_id' => $this->editor->id]);
    Post::factory()->draft()->withTranslation('en', ['title' => 'Draft marketing post'])->create();

    $this->actingAs($this->editor)->get(route('admin.posts.index', ['status' => 'draft']))
        ->assertSuccessful()->assertSee('Draft marketing post')->assertDontSee('Published engineering post');
    $this->actingAs($this->editor)->get(route('admin.posts.index', ['author' => $this->editor->id]))
        ->assertSee('Published engineering post')->assertDontSee('Draft marketing post');
    $this->actingAs($this->editor)->get(route('admin.posts.index', ['category' => $category->id]))
        ->assertSee('Published engineering post')->assertDontSee('Draft marketing post');
});

test('editors manage blog categories and tags', function () {
    $this->actingAs($this->editor)->post(route('admin.post-categories.store'), [
        'sort_order' => 1,
        'translations' => ['en' => ['name' => 'Engineering', 'slug' => 'engineering'], 'ar' => ['name' => 'الهندسة', 'slug' => 'الهندسة']],
    ])->assertRedirect(route('admin.post-categories.index'));
    $this->actingAs($this->editor)->post(route('admin.tags.store'), [
        'translations' => ['en' => ['name' => 'Laravel', 'slug' => 'laravel'], 'ar' => ['name' => 'لارافيل', 'slug' => 'لارافيل']],
    ])->assertRedirect(route('admin.post-categories.index'));

    $category = PostCategory::query()->with('translations')->sole();
    $tag = Tag::query()->with('translations')->sole();

    $this->actingAs($this->editor)->get(route('admin.post-categories.index'))->assertSuccessful()->assertSee('Engineering')->assertSee('Laravel');

    Post::factory()->create(['post_category_id' => $category->id]);
    $this->actingAs($this->editor)->delete(route('admin.post-categories.destroy', $category))->assertSessionHasErrors('category');
    $this->actingAs($this->editor)->delete(route('admin.tags.destroy', $tag))->assertRedirect();

    expect(Tag::query()->count())->toBe(0)
        ->and($category->exactTranslation('ar')->slug)->toBe('الهندسة')
        ->and(AuditLog::query()->whereIn('operation', ['post_category.created', 'tag.created', 'tag.deleted'])->count())->toBe(3);
});

test('editors maintain their public author profile', function () {
    $this->actingAs($this->editor)->put(route('admin.profile.author'), [
        'author_title' => ['en' => 'Lead Engineer', 'ar' => 'مهندس رئيسي'],
        'author_bio' => ['en' => 'Builds bilingual SaaS products.', 'ar' => ''],
    ])->assertSessionHasNoErrors();

    expect($this->editor->fresh()->authorTitle('ar'))->toBe('مهندس رئيسي')
        ->and($this->editor->fresh()->authorBio('ar'))->toBe('Builds bilingual SaaS products.');

    $this->actingAs(User::factory()->role(Role::SalesManager)->create())->put(route('admin.profile.author'), ['author_title' => ['en' => 'x']])->assertForbidden();
});

test('blog admin screens render in English and Arabic', function () {
    $post = bilingualPost(['status' => PostStatus::InReview, 'published_at' => null]);
    $category = PostCategory::factory()->withTranslation('en')->create();
    $tag = Tag::factory()->withTranslation('en')->create();

    foreach ([route('admin.posts.index'), route('admin.posts.create'), route('admin.posts.edit', $post), route('admin.posts.revisions.index', $post),
        route('admin.post-categories.index'), route('admin.post-categories.create'), route('admin.post-categories.edit', $category),
        route('admin.tags.create'), route('admin.tags.edit', $tag), route('admin.profile')] as $url) {
        $this->actingAs($this->reviewer)->get($url)->assertSuccessful();
    }

    $this->reviewer->forceFill(['locale' => 'ar'])->save();
    $this->actingAs($this->reviewer)->withSession(['locale' => 'ar'])->get(route('admin.posts.edit', $post))
        ->assertSuccessful()
        ->assertSee('سير العمل التحريري')
        ->assertSee('قائمة التحقق التحريرية');
});
