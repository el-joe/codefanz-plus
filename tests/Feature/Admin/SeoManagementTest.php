<?php

use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Enums\Role;
use App\Enums\SeoAuditStatus;
use App\Enums\SeoIssueType;
use App\Jobs\RunSeoAudit;
use App\Models\AuditLog;
use App\Models\Career;
use App\Models\ChangeRequest;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Models\SeoMeta;
use App\Models\Service;
use App\Models\User;
use App\Services\Seo\SeoScore;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seo = User::factory()->role(Role::SeoSpecialist)->create();
});

function seoService(array $en = [], array $ar = []): Service
{
    return Service::factory()
        ->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web-development', ...$en])
        ->withTranslation('ar', ['title' => 'تطوير الويب', 'slug' => 'تطوير-الويب', ...$ar])
        ->create();
}

/**
 * @return array<string, mixed>
 */
function seoMetaPayload(Service $service, array $overrides = []): array
{
    return [
        'type' => 'service',
        'target' => (string) $service->id,
        'locale' => 'en',
        'version' => $service->fresh()->lock_version,
        'seo_title' => 'Custom web development for growing companies',
        'seo_description' => 'Laravel and modern JavaScript web applications built for performance, accessibility and long-term maintainability.',
        'focus_keyword' => 'web development',
        'canonical_url' => null,
        'noindex' => '0',
        ...$overrides,
    ];
}

test('staff without the seo permission cannot open or write any SEO screen', function () {
    $editor = User::factory()->role(Role::ContentManager)->create();
    $service = seoService();

    $this->actingAs($editor)->get(route('admin.seo.audit'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.seo.meta'))->assertForbidden();
    $this->actingAs($editor)->put(route('admin.seo.meta.update'), seoMetaPayload($service))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.redirects.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.redirects.store'), ['from_path' => '/en/a', 'to_path' => '/en/b', 'status_code' => 301])->assertForbidden();
    $this->actingAs($editor)->get(route('admin.analytics.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.seo.audits.store'))->assertForbidden();
});

test('the meta editor lists every indexable item per language with scores and the rankings disclaimer', function () {
    seoService();
    Post::factory()->withTranslation('en', ['title' => 'Scaling Laravel queues'])->create();
    Career::factory()->withTranslation('en', ['title' => 'Laravel Developer'])->create();

    $this->actingAs($this->seo)->get(route('admin.seo.meta', ['type' => 'service']))->assertOk()->assertSee('Web Development')->assertSee('تطوير الويب');
    $this->actingAs($this->seo)->get(route('admin.seo.meta', ['type' => 'post']))->assertOk()->assertSee('Scaling Laravel queues');
    $this->actingAs($this->seo)->get(route('admin.seo.meta', ['q' => 'Laravel Developer']))->assertOk()->assertSee('Laravel Developer');

    $response = $this->actingAs($this->seo)->get(route('admin.seo.meta'))->assertOk();

    $response->assertSee(__('Site pages'))
        ->assertSee('they don’t guarantee rankings', false)
        ->assertSee('data-seo-row="route-home-en"', false);

    $this->actingAs($this->seo)->get(route('admin.seo.meta', ['type' => 'service', 'locale' => 'ar']))
        ->assertOk()->assertSee('تطوير الويب')->assertDontSee('data-seo-row="route-home-en"', false);
});

test('the SEO screens render in Arabic with RTL direction', function () {
    $this->seo->forceFill(['locale' => 'ar'])->save();
    seoService();

    foreach (['admin.seo.audit', 'admin.seo.meta', 'admin.redirects.index', 'admin.redirects.create', 'admin.seo.sitemap', 'admin.analytics.index'] as $route) {
        $this->actingAs($this->seo)->get(route($route))->assertOk()->assertSee('dir="rtl"', false);
    }

    $this->actingAs($this->seo)->get(route('admin.seo.audit'))->assertSee('صحة تحسين محركات البحث');
    $this->actingAs($this->seo)->get(route('admin.seo.meta'))->assertSee('الدرجات ترشد إلى الجودة');
});

test('an seo specialist updates only the SEO fields of a service; the version bumps and the change is audited', function () {
    $service = seoService();
    $version = $service->lock_version;

    $this->actingAs($this->seo)->put(route('admin.seo.meta.update'), seoMetaPayload($service, ['noindex' => '1']))
        ->assertRedirect()->assertSessionHasNoErrors();

    $service->refresh()->load('translations');
    $english = $service->exactTranslation('en');

    expect($english->seo_title)->toBe('Custom web development for growing companies')
        ->and($english->title)->toBe('Web Development')
        ->and($english->slug)->toBe('web-development')
        ->and($service->lock_version)->toBe($version + 1);

    $meta = SeoMeta::query()->whereMorphedTo('seoable', $service)->where('locale', 'en')->sole();
    expect($meta->focus_keyword)->toBe('web development')->and($meta->noindex)->toBeTrue();

    $log = AuditLog::query()->where('operation', 'seo.meta_updated')->sole();
    expect($log->user_id)->toBe($this->seo->id)
        ->and($log->after['seo_title'])->toBe('Custom web development for growing companies')
        ->and($log->after['noindex'])->toBeTrue();

    // noindex reaches the public page and keeps the URL out of the sitemap.
    $this->get(route('services.show', ['locale' => 'en', 'slug' => 'web-development']))
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('<title>Custom web development for growing companies · CodeFanz</title>', false);
    $this->get(route('sitemap.child', ['name' => 'services-en']))->assertDontSee('web-development');
});

test('a stale lock version is rejected without writing', function () {
    $service = seoService();
    $payload = seoMetaPayload($service);
    $service->forceFill(['lock_version' => $service->lock_version + 1])->save();

    $this->actingAs($this->seo)->from(route('admin.seo.meta'))->put(route('admin.seo.meta.update'), $payload)
        ->assertRedirect(route('admin.seo.meta'))
        ->assertSessionHasErrors('version');

    expect($service->fresh()->translations()->where('locale', 'en')->value('seo_title'))->toBeNull()
        ->and(AuditLog::query()->where('operation', 'seo.meta_updated')->exists())->toBeFalse();
});

test('seo fields are validated', function (array $overrides, string $field) {
    $service = seoService();

    $this->actingAs($this->seo)->put(route('admin.seo.meta.update'), seoMetaPayload($service, $overrides))->assertSessionHasErrors($field);
})->with([
    'title too long' => [['seo_title' => str_repeat('a', 71)], 'seo_title'],
    'description too long' => [['seo_description' => str_repeat('a', 171)], 'seo_description'],
    'canonical not a url' => [['canonical_url' => 'javascript:alert(1)'], 'canonical_url'],
    'unknown type' => [['type' => 'user'], 'type'],
    'unknown locale' => [['locale' => 'fr'], 'locale'],
    'unknown site page' => [['type' => 'route', 'target' => 'admin.dashboard'], 'target'],
]);

test('fixed site pages get title and description overrides with their own version', function () {
    $payload = ['type' => 'route', 'target' => 'services.index', 'locale' => 'ar', 'version' => 0, 'seo_title' => 'خدمات تطوير البرمجيات للشركات', 'seo_description' => 'وصف مخصص لصفحة الخدمات'];

    $this->actingAs($this->seo)->put(route('admin.seo.meta.update'), $payload)->assertSessionHasNoErrors();

    $meta = SeoMeta::query()->where('route_key', 'services.index')->where('locale', 'ar')->sole();
    expect($meta->lock_version)->toBe(1);

    $this->get(route('services.index', ['locale' => 'ar']))
        ->assertSee('<title>خدمات تطوير البرمجيات للشركات · CodeFanz</title>', false)
        ->assertSee('content="وصف مخصص لصفحة الخدمات"', false);
    $this->get(route('services.index', ['locale' => 'en']))->assertDontSee('خدمات تطوير البرمجيات للشركات');

    // Re-submitting the old version is stale.
    $this->actingAs($this->seo)->put(route('admin.seo.meta.update'), $payload)->assertSessionHasErrors('version');
});

test('post SEO fields are written to the article translation and recorded as a revision', function () {
    $post = Post::factory()->withTranslation('en', ['title' => 'Scaling Laravel queues'])->create();

    $this->actingAs($this->seo)->put(route('admin.seo.meta.update'), [
        'type' => 'post', 'target' => (string) $post->id, 'locale' => 'en', 'version' => $post->lock_version,
        'seo_title' => 'Scaling Laravel queues in production', 'focus_keyword' => 'laravel queues', 'canonical_url' => 'https://example.com/original',
    ])->assertSessionHasNoErrors();

    $translation = $post->translations()->where('locale', 'en')->sole();
    expect($translation->focus_keyword)->toBe('laravel queues')
        ->and($translation->canonical_url)->toBe('https://example.com/original')
        ->and($post->revisions()->count())->toBeGreaterThan(0)
        ->and(SeoMeta::query()->count())->toBe(0);
});

test('the score follows simple published rules', function () {
    $score = app(SeoScore::class);

    expect($score->evaluate([])['score'])->toBe(10)
        ->and($score->evaluate(['indexable' => false])['score'])->toBe(0)
        ->and($score->evaluate([
            'title' => 'Custom web development for growing companies',
            'description' => 'Web development with Laravel for growing companies: fast, accessible and maintainable applications.',
            'focus_keyword' => 'web development',
            'indexable' => true,
        ])['score'])->toBe(100)
        ->and($score->evaluate(['title' => 'Short', 'focus_keyword' => 'web', 'indexable' => true])['score'])->toBe(35)
        ->and($score->evaluate(['title' => 'x'])['checks'])->toHaveCount(8);
});

test('redirects are created, normalized, listed and deleted with audit entries', function () {
    $this->actingAs($this->seo)->post(route('admin.redirects.store'), ['from_path' => 'http://localhost/en/old-page/?utm=1', 'to_path' => '/en/services', 'status_code' => 301, 'is_active' => '1'])
        ->assertRedirect(route('admin.redirects.index'));

    $redirect = Redirect::query()->sole();
    expect($redirect->from_path)->toBe('/en/old-page')->and($redirect->created_by)->toBe($this->seo->id);

    $this->actingAs($this->seo)->get(route('admin.redirects.index'))->assertOk()->assertSee('/en/old-page');
    $this->actingAs($this->seo)->delete(route('admin.redirects.destroy', $redirect))->assertRedirect();

    expect(Redirect::query()->count())->toBe(0)
        ->and(AuditLog::query()->whereIn('operation', ['redirect.created', 'redirect.deleted'])->count())->toBe(2);
});

test('redirect loops, chains, self-redirects and unsafe targets are rejected', function () {
    Redirect::create(['from_path' => '/en/a', 'to_path' => '/en/b', 'status_code' => 301]);

    $post = fn (array $data) => $this->actingAs($this->seo)->post(route('admin.redirects.store'), [...$data, 'is_active' => '1']);

    $post(['from_path' => '/en/b', 'to_path' => '/en/a', 'status_code' => 301])->assertSessionHasErrors('to_path');
    $post(['from_path' => '/en/c', 'to_path' => '/en/a', 'status_code' => 301])->assertSessionHasErrors('to_path');
    $post(['from_path' => '/en/b', 'to_path' => '/en/d', 'status_code' => 301])->assertSessionHasErrors('from_path');
    $post(['from_path' => '/en/x', 'to_path' => '/en/x/', 'status_code' => 301])->assertSessionHasErrors('to_path');
    $post(['from_path' => '/en/x', 'to_path' => 'javascript:alert(1)', 'status_code' => 301])->assertSessionHasErrors('to_path');
    $post(['from_path' => '/en/x', 'to_path' => 'http://evil.example/', 'status_code' => 301])->assertSessionHasErrors('to_path');
    $post(['from_path' => '/en/a', 'to_path' => '/en/z', 'status_code' => 302])->assertSessionHasErrors('from_path');
    $post(['from_path' => '/en/x', 'status_code' => 301])->assertSessionHasErrors('to_path');

    $post(['from_path' => '/en/gone', 'status_code' => 410])->assertSessionHasNoErrors();
    $post(['from_path' => '/en/x', 'to_path' => 'https://partner.example/page', 'status_code' => 302])->assertSessionHasNoErrors();

    expect(Redirect::query()->count())->toBe(3);
});

test('redirects import from CSV and report rejected lines', function () {
    $csv = "from,to,status\n/en/old-a,/en/new-a,301\n/en/old-b,,410\n/en/new-a,/en/old-a,301\n/en/bad,/en/x,307\n";

    $this->actingAs($this->seo)->post(route('admin.redirects.import'), ['file' => UploadedFile::fake()->createWithContent('redirects.csv', $csv)])
        ->assertRedirect(route('admin.redirects.index'))
        ->assertSessionHas('importErrors', fn (array $errors): bool => count($errors) === 2);

    expect(Redirect::query()->pluck('status_code', 'from_path')->all())->toBe(['/en/old-a' => 301, '/en/old-b' => 410])
        ->and(AuditLog::query()->where('operation', 'redirect.imported')->sole()->after)->toBe(['imported' => 2, 'rejected' => 2]);
});

test('run audit queues a crawl once and the dashboard shows the health score with the disclaimer', function () {
    Queue::fake();

    $this->actingAs($this->seo)->post(route('admin.seo.audits.store'))->assertRedirect(route('admin.seo.audit'));
    $this->actingAs($this->seo)->post(route('admin.seo.audits.store'))->assertSessionHas('toast', __('An audit is already running.'));

    Queue::assertPushed(RunSeoAudit::class, 1);
    expect(SeoAudit::query()->sole()->status)->toBe(SeoAuditStatus::Queued);

    $audit = SeoAudit::create(['status' => SeoAuditStatus::Completed, 'pages_crawled' => 12, 'health_score' => 82, 'finished_at' => now()]);
    SeoIssue::create(['seo_audit_id' => $audit->id, 'type' => SeoIssueType::BrokenInternalLink, 'category' => 'links', 'severity' => 'error', 'url' => 'http://localhost/en/blog', 'locale' => 'en', 'details' => ['link' => 'http://localhost/en/blog/old', 'status' => 404]]);

    $this->actingAs($this->seo)->get(route('admin.seo.audit'))->assertOk()
        ->assertSee('data-health-score>82<', false)
        ->assertSee('doesn’t guarantee rankings', false)
        ->assertSee(__('Broken internal link'))
        ->assertSee(route('admin.redirects.create', ['from' => 'http://localhost/en/blog/old']), false);
});

function configureSeoAi(): void
{
    $settings = app(Settings::class);
    $settings->set('integrations', 'ai_provider', 'openai');
    $settings->set('integrations', 'openai_api_key', 'test-key-openai', encrypted: true);
}

function seoIssueFor(Service $service, SeoIssueType $type = SeoIssueType::MissingDescription): SeoIssue
{
    $audit = SeoAudit::create(['status' => SeoAuditStatus::Completed, 'pages_crawled' => 1, 'health_score' => 70, 'finished_at' => now()]);

    return SeoIssue::create([
        'seo_audit_id' => $audit->id, 'type' => $type, 'category' => $type->category(), 'severity' => $type->severity(),
        'url' => route('services.show', ['locale' => 'en', 'slug' => 'web-development']), 'locale' => 'en',
        'seoable_type' => 'service', 'seoable_id' => $service->id,
    ]);
}

test('the AI fix button is disabled with an honest note when AI is not configured', function () {
    $service = seoService();
    seoIssueFor($service);

    $this->actingAs($this->seo)->get(route('admin.seo.audit'))->assertOk()
        ->assertSee('data-ai-fix-disabled', false)
        ->assertSee(__('Not configured'))
        ->assertDontSee(route('admin.seo.issues.ai-fix', SeoIssue::query()->sole()), false);

    $this->actingAs($this->seo)->post(route('admin.seo.issues.ai-fix', SeoIssue::query()->sole()))->assertSessionHasErrors('ai');
    expect(ChangeRequest::query()->count())->toBe(0);
});

test('AI fix creates a pending AI change request and never applies it', function () {
    configureSeoAi();
    Http::preventStrayRequests();
    Http::fake(['api.openai.com/*' => Http::response([
        'id' => 'chatcmpl-test', 'model' => 'gpt-4.1-mini',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode(['seo_title' => 'Web development services for growing teams', 'seo_description' => 'Fast, accessible Laravel web applications designed, built and maintained by an experienced team.'])], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 60],
    ])]);
    $service = seoService();
    $version = $service->lock_version;
    $issue = seoIssueFor($service);

    $this->actingAs($this->seo)->get(route('admin.seo.audit'))->assertSee(route('admin.seo.issues.ai-fix', $issue), false)->assertDontSee('data-ai-fix-disabled', false);

    $this->actingAs($this->seo)->post(route('admin.seo.issues.ai-fix', $issue))->assertRedirect();

    $changeRequest = ChangeRequest::query()->sole();
    expect($changeRequest->origin)->toBe(ChangeOrigin::Ai)
        ->and($changeRequest->status)->toBe(ChangeRequestStatus::Pending)
        ->and($changeRequest->locale)->toBe('en')
        ->and($changeRequest->proposed)->toBe(['seo_title' => 'Web development services for growing teams', 'seo_description' => 'Fast, accessible Laravel web applications designed, built and maintained by an experienced team.'])
        ->and($changeRequest->required_permission->value)->toBe('seo.approve');

    $service->refresh();
    expect($service->lock_version)->toBe($version)
        ->and($service->translations()->where('locale', 'en')->value('seo_description'))->toBeNull();
});

test('AI fix is refused for issue types it cannot address and for hand-edited items', function () {
    configureSeoAi();
    Http::preventStrayRequests();
    $service = seoService();

    $this->actingAs($this->seo)->post(route('admin.seo.issues.ai-fix', seoIssueFor($service, SeoIssueType::MissingAlt)))->assertSessionHasErrors('ai');

    $career = Career::factory()->withTranslation('en')->create();
    $issue = seoIssueFor($service);
    $issue->forceFill(['seoable_type' => 'career', 'seoable_id' => $career->id])->save();

    $this->actingAs($this->seo)->post(route('admin.seo.issues.ai-fix', $issue))->assertSessionHasErrors('ai');
    expect(ChangeRequest::query()->count())->toBe(0);
});
