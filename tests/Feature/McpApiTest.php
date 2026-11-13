<?php

use App\Actions\Governance\ReviewChangeRequest;
use App\Actions\Mcp\ManageMcpToken;
use App\Enums\ActorType;
use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Enums\KillSwitchKey;
use App\Enums\LeadSource;
use App\Enums\McpCallResult;
use App\Enums\McpScope;
use App\Enums\PostOrigin;
use App\Enums\PostStatus;
use App\Enums\Role;
use App\Enums\SeoAuditStatus;
use App\Enums\SocialPostStatus;
use App\Events\LeadCreated;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\Lead;
use App\Models\McpClient;
use App\Models\McpToolCall;
use App\Models\Page;
use App\Models\Post;
use App\Models\SeoAudit;
use App\Models\Service;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\ChangeRequestProposed;
use App\Notifications\NewLeadReceived;
use App\Services\KillSwitches;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

/**
 * A client with the given scopes and a freshly issued token.
 *
 * @param  list<McpScope>  $scopes
 * @param  array<string, mixed>  $attributes
 * @return array{0: McpClient, 1: string}
 */
function mcpClient(array $scopes = [McpScope::ContentRead], array $attributes = []): array
{
    $client = McpClient::factory()->scopes($scopes)->create($attributes);
    $token = app(ManageMcpToken::class)->issue($client, $client->owner);

    return [$client->fresh(), $token];
}

/**
 * Forget the resolved Sanctum user so the next request authenticates again (several tokens in one test).
 */
function forgetMcpAuth(): void
{
    app('auth')->forgetGuards();
}

beforeEach(function () {
    $this->service = Service::factory()->withTranslation('en', ['title' => 'Web development', 'slug' => 'web-development', 'seo_title' => 'Web | CodeFanz'])->create();
    $this->draftService = Service::factory()->draft()->withTranslation('en', ['title' => 'Secret draft', 'slug' => 'secret-draft'])->create();
});

test('a valid token can call whoami without revealing the token', function () {
    [$client, $token] = mcpClient();

    $this->withToken($token)->getJson('/api/v1/mcp/me')
        ->assertOk()
        ->assertJsonPath('data.name', $client->name)
        ->assertJsonPath('data.scopes', [McpScope::ContentRead->value])
        ->assertDontSee($token);
});

test('missing or invalid tokens get the error envelope', function () {
    $this->getJson('/api/v1/mcp/me')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    $this->withToken('1|not-a-real-token')->getJson('/api/v1/mcp/me')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
});

test('every endpoint refuses a token without its scope', function (string $method, string $uri, McpScope $scope) {
    [, $token] = mcpClient([$scope === McpScope::ContentRead ? McpScope::SeoRead : McpScope::ContentRead]);

    $this->withToken($token)->json($method, $uri, [])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_scope')
        ->assertJsonPath('error.details.required_scope', $scope->value);
})->with([
    'list_content' => ['GET', '/api/v1/mcp/content/service', McpScope::ContentRead],
    'propose_content_change' => ['POST', '/api/v1/mcp/content/service/1/proposals', McpScope::ContentDraft],
    'create_blog_draft' => ['POST', '/api/v1/mcp/posts/drafts', McpScope::ContentDraft],
    'list_leads' => ['GET', '/api/v1/mcp/leads', McpScope::LeadsRead],
    'create_lead' => ['POST', '/api/v1/mcp/leads', McpScope::LeadsWrite],
    'get_seo_audit' => ['GET', '/api/v1/mcp/seo/audit', McpScope::SeoRead],
    'propose_seo_meta' => ['POST', '/api/v1/mcp/seo/proposals', McpScope::SeoPropose],
    'get_analytics_summary' => ['GET', '/api/v1/mcp/analytics/summary', McpScope::AnalyticsRead],
    'list_approvals' => ['GET', '/api/v1/mcp/approvals', McpScope::ApprovalsRead],
    'apply_approved_change' => ['POST', '/api/v1/mcp/approvals/CR-2026-0001/apply', McpScope::ApprovalsApply],
    'create_social_draft' => ['POST', '/api/v1/mcp/social/drafts', McpScope::SocialDraft],
]);

test('disabled, expired and IP-blocked clients are refused', function () {
    [$client, $token] = mcpClient();

    $client->update(['status' => 'disabled']);
    $this->withToken($token)->getJson('/api/v1/mcp/content/service')->assertForbidden()->assertJsonPath('error.code', 'client_disabled');

    forgetMcpAuth();
    $client->update(['status' => 'active', 'allowed_ips' => ['10.0.0.0/8']]);
    $this->withToken($token)->getJson('/api/v1/mcp/content/service')->assertForbidden()->assertJsonPath('error.code', 'ip_not_allowed');

    forgetMcpAuth();
    $client->update(['allowed_ips' => ['127.0.0.0/8']]);
    $this->withToken($token)->getJson('/api/v1/mcp/content/service')->assertOk();

    forgetMcpAuth();
    $client->update(['expires_at' => now()->subMinute()]);
    $this->withToken($token)->getJson('/api/v1/mcp/content/service')->assertUnauthorized()->assertJsonPath('error.code', 'client_expired');
});

test('an expired or revoked token is rejected by Sanctum', function () {
    [$client, $token] = mcpClient();
    $client->tokens()->update(['expires_at' => now()->subMinute()]);

    $this->withToken($token)->getJson('/api/v1/mcp/me')->assertUnauthorized();

    forgetMcpAuth();
    [$other, $otherToken] = mcpClient();
    app(ManageMcpToken::class)->revoke($other, $other->owner);

    $this->withToken($otherToken)->getJson('/api/v1/mcp/me')->assertUnauthorized();
});

test('an owner who lost the permission cannot use the scope', function () {
    $owner = User::factory()->role(Role::Developer)->create();
    [, $token] = mcpClient([McpScope::LeadsRead], ['owner_id' => $owner->id]);

    $this->withToken($token)->getJson('/api/v1/mcp/leads')->assertForbidden()->assertJsonPath('error.code', 'owner_not_permitted');
});

test('the MCP access kill switch blocks every call, including reads', function () {
    [$client, $token] = mcpClient();
    app(KillSwitches::class)->set(KillSwitchKey::McpAccess, true, $client->owner);

    $this->withToken($token)->getJson('/api/v1/mcp/content/service')->assertStatus(503)->assertJsonPath('error.code', 'mcp_disabled');
    $this->withToken($token)->getJson('/api/v1/mcp/me')->assertStatus(503);

    expect(McpToolCall::where('result', McpCallResult::Denied)->count())->toBe(2);
});

test('the MCP writes kill switch blocks write tools but not reads', function () {
    [$client, $token] = mcpClient([McpScope::ContentRead, McpScope::ContentDraft]);
    app(KillSwitches::class)->set(KillSwitchKey::McpWrites, true, $client->owner);

    $this->withToken($token)->postJson("/api/v1/mcp/content/service/{$this->service->id}/proposals", ['locale' => 'en', 'changes' => ['title' => 'New']])
        ->assertStatus(503)->assertJsonPath('error.code', 'mcp_writes_disabled');
    $this->withToken($token)->getJson('/api/v1/mcp/content/service')->assertOk();

    expect(ChangeRequest::count())->toBe(0);
});

test('the per-client rate limit answers 429 with Retry-After', function () {
    [, $token] = mcpClient(attributes: ['rate_limit_per_minute' => 2]);

    $this->withToken($token)->getJson('/api/v1/mcp/me')->assertOk();
    $this->withToken($token)->getJson('/api/v1/mcp/me')->assertOk();
    $this->withToken($token)->getJson('/api/v1/mcp/me')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error.code', 'rate_limited');

    expect(McpToolCall::where('result', McpCallResult::RateLimited)->count())->toBe(1);
});

test('content lists only published items unless the client may draft', function () {
    [, $token] = mcpClient();

    $this->withToken($token)->getJson('/api/v1/mcp/content/service?locale=en')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.translations.en.title', 'Web development')
        ->assertJsonStructure(['data', 'meta' => ['per_page', 'next_cursor', 'prev_cursor']]);

    $this->withToken($token)->getJson('/api/v1/mcp/content/service?status=all')->assertForbidden();
    $this->withToken($token)->getJson("/api/v1/mcp/content/service/{$this->draftService->id}")->assertNotFound()->assertJsonPath('error.code', 'not_found');

    forgetMcpAuth();
    [, $draftToken] = mcpClient([McpScope::ContentRead, McpScope::ContentDraft]);

    $this->withToken($draftToken)->getJson('/api/v1/mcp/content/service?status=all')->assertOk()->assertJsonCount(2, 'data');
    $this->withToken($draftToken)->getJson('/api/v1/mcp/content/service/secret-draft?locale=en')->assertOk()->assertJsonPath('data.status', 'draft');
});

test('content can be fetched by slug with every editable field and version', function () {
    [, $token] = mcpClient();

    $this->withToken($token)->getJson('/api/v1/mcp/content/service/web-development')
        ->assertOk()
        ->assertJsonPath('data.id', $this->service->id)
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.translations.en.seo_title', 'Web | CodeFanz')
        ->assertJsonPath('data.is_public', true);
});

test('cursor pagination walks through every item', function () {
    Service::factory()->count(3)->withTranslation('en')->create();
    [, $token] = mcpClient();

    $first = $this->withToken($token)->getJson('/api/v1/mcp/content/service?per_page=2')->assertOk()->assertJsonCount(2, 'data');
    $cursor = $first->json('meta.next_cursor');

    expect($cursor)->not->toBeNull();

    $this->withToken($token)->getJson('/api/v1/mcp/content/service?per_page=2&cursor='.$cursor)->assertOk()->assertJsonCount(2, 'data');
});

test('proposing a content change creates a pending MCP change request and never touches the target', function () {
    Notification::fake();
    $reviewer = User::factory()->role(Role::ContentManager)->create();
    [$client, $token] = mcpClient([McpScope::ContentDraft]);

    $response = $this->withToken($token)->postJson("/api/v1/mcp/content/service/{$this->service->id}/proposals", [
        'locale' => 'en',
        'changes' => ['title' => 'Custom web development', 'summary' => 'Fast, accessible websites.'],
        'expected_version' => 1,
    ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.origin', 'mcp');

    $changeRequest = ChangeRequest::firstWhere('reference', $response->json('data.reference'));

    expect($changeRequest->origin)->toBe(ChangeOrigin::Mcp)
        ->and($changeRequest->status)->toBe(ChangeRequestStatus::Pending)
        ->and($changeRequest->requested_by)->toBe($client->owner_id)
        ->and($changeRequest->actor_label)->toBe('MCP · '.$client->name)
        ->and($this->service->fresh()->translation('en')->title)->toBe('Web development')
        ->and($this->service->fresh()->lock_version)->toBe(1)
        ->and(McpToolCall::where('tool', 'propose_content_change')->value('change_request_id'))->toBe($changeRequest->id)
        ->and(AuditLog::where('operation', 'change_request.proposed')->where('actor_type', ActorType::McpClient)->exists())->toBeTrue();

    Notification::assertSentTo($reviewer, ChangeRequestProposed::class);
});

test('proposals reject unknown fields, unchanged content and stale versions', function () {
    [, $token] = mcpClient([McpScope::ContentDraft]);
    $uri = "/api/v1/mcp/content/service/{$this->service->id}/proposals";

    $this->withToken($token)->postJson($uri, ['locale' => 'en', 'changes' => ['status' => 'published']])
        ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    $this->withToken($token)->postJson($uri, ['locale' => 'en', 'changes' => ['title' => 'Web development']])
        ->assertUnprocessable();
    $this->withToken($token)->postJson($uri, ['locale' => 'en', 'changes' => ['title' => 'Other'], 'expected_version' => 7])
        ->assertStatus(409)->assertJsonPath('error.code', 'stale_version');
    $this->withToken($token)->postJson($uri, ['locale' => 'ar', 'changes' => ['title' => 'تطوير الويب']])
        ->assertUnprocessable()->assertJsonValidationErrors('changes.slug', 'error.details.fields');

    expect(ChangeRequest::count())->toBe(0);
});

test('proposing SEO metadata creates a pending change request needing seo approval', function () {
    [, $token] = mcpClient([McpScope::SeoPropose]);

    $this->withToken($token)->postJson('/api/v1/mcp/seo/proposals', [
        'type' => 'service', 'id' => $this->service->id, 'locale' => 'en',
        'seo_title' => 'Web Development Services | CodeFanz',
    ])->assertCreated()
        ->assertJsonPath('data.required_permission', 'seo.approve')
        ->assertJsonPath('data.proposed.seo_title', 'Web Development Services | CodeFanz');

    expect(ChangeRequest::sole()->origin)->toBe(ChangeOrigin::Mcp)
        ->and($this->service->fresh()->translation('en')->seo_title)->toBe('Web | CodeFanz');
});

test('a blog draft is created as a draft with origin mcp that its owner cannot approve', function () {
    $owner = User::factory()->role(Role::ContentManager)->create();
    [$client, $token] = mcpClient([McpScope::ContentDraft], ['owner_id' => $owner->id]);

    $response = $this->withToken($token)->postJson('/api/v1/mcp/posts/drafts', [
        'translations' => ['en' => ['title' => 'Shipping Arabic-first apps', 'body' => "## Why\n\nBecause **RTL** matters. <script>alert(1)</script>"]],
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.origin', 'mcp');

    $post = Post::with('translations')->find($response->json('data.id'));

    expect($post->status)->toBe(PostStatus::Draft)
        ->and($post->origin)->toBe(PostOrigin::Mcp)
        ->and($post->isAiOrigin())->toBeTrue()
        ->and($post->author_id)->toBe($client->owner_id)
        ->and($post->translation('en')->slug)->toBe('shipping-arabic-first-apps')
        ->and($post->translation('en')->body)->toContain('<strong>RTL</strong>')->not->toContain('<script>')
        ->and($client->owner->can('review', $post))->toBeFalse();
});

test('creating a lead records source mcp, fires events and notifies the team', function () {
    Event::fake([LeadCreated::class]);
    Notification::fake();
    $sales = User::factory()->role(Role::SalesManager)->create();
    [$client, $token] = mcpClient([McpScope::LeadsWrite]);

    $this->withToken($token)->postJson('/api/v1/mcp/leads', [
        'name' => 'Layla Hassan', 'email' => 'Layla@Example.test', 'preferred_locale' => 'ar',
        'description' => 'Needs a booking app', 'budget_range' => '10k_25k', 'services' => [$this->service->id],
    ])->assertCreated()
        ->assertJsonPath('data.source', 'mcp')
        ->assertJsonMissingPath('data.email');

    $lead = Lead::sole();

    expect($lead->source)->toBe(LeadSource::Mcp)
        ->and($lead->email)->toBe('layla@example.test')
        ->and($lead->created_by)->toBe($client->owner_id);

    Event::assertDispatched(LeadCreated::class);
    Notification::assertSentTo($sales, NewLeadReceived::class);

    $logged = McpToolCall::sole();
    expect($logged->arguments['name'])->toBe('[redacted]')
        ->and($logged->arguments['email'])->toBe('[redacted]')
        ->and(json_encode($logged->arguments))->not->toContain('Layla');
});

test('lead reads minimise personal data unless the client allows it', function () {
    Lead::factory()->create(['name' => 'Omar Said', 'email' => 'omar@example.test']);
    [$client, $token] = mcpClient([McpScope::LeadsRead]);

    $this->withToken($token)->getJson('/api/v1/mcp/leads')->assertOk()
        ->assertJsonPath('data.0.personal_data_included', false)
        ->assertJsonMissingPath('data.0.email')
        ->assertDontSee('omar@example.test');

    forgetMcpAuth();
    $client->update(['allow_lead_pii' => true]);
    $reference = Lead::sole()->reference;

    $this->withToken($token)->getJson("/api/v1/mcp/leads/{$reference}")->assertOk()->assertJsonPath('data.email', 'omar@example.test');
});

test('seo audit and analytics summaries are read from stored data only', function () {
    SeoAudit::create(['status' => SeoAuditStatus::Completed, 'pages_crawled' => 12, 'health_score' => 87, 'summary' => ['by_severity' => ['warning' => 2]], 'started_at' => now(), 'finished_at' => now()]);
    [, $token] = mcpClient([McpScope::SeoRead, McpScope::AnalyticsRead]);

    $this->withToken($token)->getJson('/api/v1/mcp/seo/audit')->assertOk()->assertJsonPath('data.health_score', 87);
    $this->withToken($token)->getJson('/api/v1/mcp/seo/issues')->assertOk()->assertJsonCount(0, 'data');
    $this->withToken($token)->getJson('/api/v1/mcp/analytics/summary?days=7')->assertOk()
        ->assertJsonPath('data.days', 7)
        ->assertJsonPath('data.ga4.state', 'not_configured');
});

test('a social draft is created with origin mcp and stays a draft', function () {
    $account = SocialAccount::factory()->connected()->create();
    [$client, $token] = mcpClient([McpScope::SocialDraft]);

    $this->withToken($token)->getJson('/api/v1/mcp/social/accounts')->assertOk()->assertJsonPath('data.0.id', $account->id)->assertJsonMissingPath('data.0.access_token');

    $this->withToken($token)->postJson('/api/v1/mcp/social/drafts', [
        'title' => 'Launch post', 'locale' => 'en',
        'variants' => [['account_id' => $account->id, 'text' => 'We just launched our new site.']],
    ])->assertCreated()->assertJsonPath('data.status', 'draft');

    $post = SocialPost::sole();

    expect($post->status)->toBe(SocialPostStatus::Draft)
        ->and($post->origin)->toBe(PostOrigin::Mcp)
        ->and($post->isGeneratedBy($client->owner))->toBeTrue();
});

test('approvals list shows status, and apply refuses requests a human has not approved', function () {
    [$client, $token] = mcpClient([McpScope::ContentDraft, McpScope::ApprovalsRead, McpScope::ApprovalsApply]);
    $reference = $this->withToken($token)->postJson("/api/v1/mcp/content/service/{$this->service->id}/proposals", ['locale' => 'en', 'changes' => ['title' => 'Custom web development']])->json('data.reference');

    $this->withToken($token)->getJson('/api/v1/mcp/approvals?mine=1')->assertOk()->assertJsonPath('data.0.reference', $reference)->assertJsonMissingPath('data.0.proposed');
    $this->withToken($token)->getJson("/api/v1/mcp/approvals/{$reference}")->assertOk()->assertJsonPath('data.proposed.title', 'Custom web development');

    $this->withToken($token)->postJson("/api/v1/mcp/approvals/{$reference}/apply")
        ->assertStatus(409)->assertJsonPath('error.code', 'not_approved');

    expect($this->service->fresh()->translation('en')->title)->toBe('Web development');
});

test('apply works for a human-approved MCP request and refuses stale ones', function () {
    $reviewer = User::factory()->role(Role::ContentManager)->create();
    [$client, $token] = mcpClient([McpScope::ContentDraft, McpScope::ApprovalsRead, McpScope::ApprovalsApply]);
    $uri = "/api/v1/mcp/content/service/{$this->service->id}/proposals";

    $first = $this->withToken($token)->postJson($uri, ['locale' => 'en', 'changes' => ['title' => 'Custom web development']])->json('data.reference');
    $second = $this->withToken($token)->postJson($uri, ['locale' => 'en', 'changes' => ['summary' => 'Something else']])->json('data.reference');

    app(ReviewChangeRequest::class)->approve(ChangeRequest::firstWhere('reference', $first), $reviewer);
    app(ReviewChangeRequest::class)->approve(ChangeRequest::firstWhere('reference', $second), $reviewer);

    $this->withToken($token)->postJson("/api/v1/mcp/approvals/{$first}/apply")->assertOk()->assertJsonPath('data.status', 'applied');

    expect($this->service->fresh()->translation('en')->title)->toBe('Custom web development')
        ->and(AuditLog::where('operation', 'mcp.change_applied')->exists())->toBeTrue();

    $this->withToken($token)->postJson("/api/v1/mcp/approvals/{$second}/apply")->assertStatus(409)->assertJsonPath('error.code', 'stale_version');

    expect(ChangeRequest::firstWhere('reference', $second)->status)->toBe(ChangeRequestStatus::Failed);
});

test('apply refuses change requests that did not come from MCP', function () {
    [$client, $token] = mcpClient([McpScope::ApprovalsRead, McpScope::ApprovalsApply]);
    $changeRequest = ChangeRequest::factory()->create(['origin' => ChangeOrigin::Ai, 'status' => ChangeRequestStatus::Approved]);

    $this->withToken($token)->postJson("/api/v1/mcp/approvals/{$changeRequest->reference}/apply")
        ->assertForbidden()->assertJsonPath('error.code', 'origin_not_allowed');
});

test('the tool-call log stores sanitized arguments, outcome and latency', function () {
    [$client, $token] = mcpClient([McpScope::ContentDraft]);

    $this->withToken($token)->postJson("/api/v1/mcp/content/service/{$this->service->id}/proposals", [
        'locale' => 'en',
        'changes' => ['title' => str_repeat('Long title ', 5)],
        'api_key' => 'sk-should-never-be-stored',
        'summary' => 'Contact me at someone@example.test',
    ])->assertCreated();

    $call = McpToolCall::sole();

    expect($call->tool)->toBe('propose_content_change')
        ->and($call->result)->toBe(McpCallResult::Ok)
        ->and($call->http_status)->toBe(201)
        ->and($call->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($call->arguments['api_key'])->toBe('[redacted]')
        ->and($call->arguments['summary'])->toBe('Contact me at [email]')
        ->and($call->arguments['type'])->toBe('service')
        ->and(json_encode($call->arguments))->not->toContain($token)->not->toContain('sk-should-never');

    expect($client->fresh()->last_used_at)->not->toBeNull();
});

test('old tool calls are pruned after the retention window', function () {
    [$client] = mcpClient();
    McpToolCall::create(['mcp_client_id' => $client->id, 'tool' => 'whoami', 'method' => 'GET', 'result' => McpCallResult::Ok, 'http_status' => 200, 'duration_ms' => 3, 'created_at' => now()->subDays(91)]);
    McpToolCall::create(['mcp_client_id' => $client->id, 'tool' => 'whoami', 'method' => 'GET', 'result' => McpCallResult::Ok, 'http_status' => 200, 'duration_ms' => 3, 'created_at' => now()->subDays(5)]);

    $this->artisan('model:prune', ['--model' => [McpToolCall::class]])->assertSuccessful();

    expect(McpToolCall::count())->toBe(1);
});

test('mcp page entries for the database are never created for unknown content types', function () {
    [, $token] = mcpClient();

    $this->withToken($token)->getJson('/api/v1/mcp/content/user')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    expect(Page::count())->toBe(0);
});
