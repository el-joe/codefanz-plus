<?php

use App\Enums\Role;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Jobs\RefreshSocialAccountToken;
use App\Models\AuditLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Settings;
use App\Services\Social\SocialOAuth;
use App\Services\Social\SocialTokenRefresher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->manager = User::factory()->role(Role::SocialMediaManager)->create();
});

function configureSocialApp(string $provider): void
{
    $settings = app(Settings::class);
    [$id, $secret] = config("codefanz.social.providers.{$provider}.credentials");
    $settings->set('social', $id, $provider === 'meta' ? '123456789' : 'client-'.$provider);
    $settings->set('social', $secret, 'secret-'.$provider, encrypted: true);
}

/**
 * Start the connect flow and return the state the platform would echo back.
 *
 * @return array{state: string, query: array<string, string>}
 */
function startSocialConnect(object $test, User $user, string $provider): array
{
    $response = $test->actingAs($user)->get(route('admin.social.accounts.connect', $provider));
    $response->assertRedirect();
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return ['state' => $query['state'], 'query' => $query];
}

test('the accounts page shows honest statuses per app and staff without social.manage are refused', function () {
    $this->actingAs($this->manager)->get(route('admin.social.accounts.index'))
        ->assertOk()
        ->assertSee('data-app-status="requires_credentials"', false)
        ->assertDontSee('data-app-status="connected"', false)
        ->assertSee(route('admin.social.oauth.callback', 'linkedin'))
        ->assertSee(__('Manual export'));

    $sales = User::factory()->role(Role::SalesManager)->create();
    $this->actingAs($sales)->get(route('admin.social.accounts.index'))->assertForbidden();
    $this->actingAs($sales)->get(route('admin.social.posts.index'))->assertForbidden();
});

test('the accounts page renders right-to-left in arabic', function () {
    SocialAccount::factory()->connected(SocialPlatform::X)->create(['name' => 'CodeFanz']);
    $this->manager->forceFill(['locale' => 'ar'])->save();

    $this->actingAs($this->manager)->get(route('admin.social.accounts.index'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('حسابات التواصل الاجتماعي')
        ->assertSee('متصل');
});

test('connecting without app credentials is refused with a clear message', function () {
    $this->actingAs($this->manager)->get(route('admin.social.accounts.connect', 'x'))
        ->assertRedirect(route('admin.social.accounts.index'))
        ->assertSessionHas('toast');

    Http::assertNothingSent();
});

test('x uses a state and a PKCE S256 challenge, and the callback stores the tokens encrypted', function () {
    configureSocialApp('x');
    ['state' => $state, 'query' => $query] = startSocialConnect($this, $this->manager, 'x');

    expect($query['code_challenge_method'])->toBe('S256')
        ->and($query['client_id'])->toBe('client-x')
        ->and($query['redirect_uri'])->toBe(route('admin.social.oauth.callback', 'x'))
        ->and($query['scope'])->toContain('tweet.write')
        ->and(strlen($state))->toBe(40);

    $verifier = session('social_oauth.x.verifier');
    expect(SocialOAuth::challenge($verifier))->toBe($query['code_challenge']);

    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response(['access_token' => 'x-access-123', 'refresh_token' => 'x-refresh-456', 'expires_in' => 7200, 'scope' => 'tweet.read tweet.write users.read offline.access', 'token_type' => 'bearer']),
        'api.x.com/2/users/me' => Http::response(['data' => ['id' => '987654', 'name' => 'CodeFanz', 'username' => 'codefanz']]),
    ]);

    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'x', 'state' => $state, 'code' => 'auth-code']))
        ->assertRedirect(route('admin.social.accounts.index'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/oauth2/token'
        && $request['code_verifier'] === $verifier
        && $request['code'] === 'auth-code'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('client-x:secret-x')));

    $account = SocialAccount::query()->sole();
    $raw = DB::table('social_accounts')->where('id', $account->id)->first();

    expect($account->platform)->toBe(SocialPlatform::X)
        ->and($account->status)->toBe(SocialAccountStatus::Connected)
        ->and($account->external_id)->toBe('987654')
        ->and($account->handle)->toBe('codefanz')
        ->and($account->access_token)->toBe('x-access-123')
        ->and($account->refresh_token)->toBe('x-refresh-456')
        ->and($account->token_expires_at->isFuture())->toBeTrue()
        ->and($raw->access_token)->not->toContain('x-access-123')
        ->and($raw->refresh_token)->not->toContain('x-refresh-456')
        ->and(AuditLog::query()->where('operation', 'social_account.connected')->exists())->toBeTrue();

    // The state is single use.
    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'x', 'state' => $state, 'code' => 'auth-code']));
    Http::assertSentCount(2);
});

test('an invalid, missing or expired state is rejected before any token request', function () {
    configureSocialApp('x');
    ['state' => $state] = startSocialConnect($this, $this->manager, 'x');

    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'x', 'state' => 'forged-'.$state, 'code' => 'auth-code']))
        ->assertRedirect(route('admin.social.accounts.index'));

    ['state' => $fresh] = startSocialConnect($this, $this->manager, 'x');
    $this->travel(11)->minutes();
    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'x', 'state' => $fresh, 'code' => 'auth-code']));

    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'x', 'code' => 'auth-code']));

    Http::assertNothingSent();
    expect(SocialAccount::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('operation', 'social_account.connect_rejected')->where('result', 'failure')->count())->toBe(3);
});

test('another staff member cannot complete someone else\'s connection', function () {
    configureSocialApp('x');
    ['state' => $state] = startSocialConnect($this, $this->manager, 'x');
    $other = User::factory()->role(Role::SocialMediaManager)->create();

    $this->actingAs($other)->withSession(['social_oauth' => session('social_oauth')])
        ->get(route('admin.social.oauth.callback', ['provider' => 'x', 'state' => $state, 'code' => 'auth-code']));

    Http::assertNothingSent();
    expect(SocialAccount::query()->count())->toBe(0);
});

test('linkedin connects every administered page, without PKCE', function () {
    configureSocialApp('linkedin');
    ['state' => $state, 'query' => $query] = startSocialConnect($this, $this->manager, 'linkedin');
    expect($query)->not->toHaveKey('code_challenge');

    Http::fake([
        'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li-access', 'expires_in' => 5184000, 'refresh_token' => 'li-refresh', 'refresh_token_expires_in' => 31536000, 'scope' => 'r_organization_social,w_organization_social']),
        'api.linkedin.com/rest/organizationAcls*' => Http::response(['elements' => [['organization' => 'urn:li:organization:111', 'role' => 'ADMINISTRATOR']]]),
        'api.linkedin.com/rest/organizations/111' => Http::response(['id' => 111, 'localizedName' => 'CodeFanz', 'vanityName' => 'codefanz']),
    ]);

    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'linkedin', 'state' => $state, 'code' => 'li-code']))->assertRedirect();

    $account = SocialAccount::query()->sole();
    expect($account->platform)->toBe(SocialPlatform::LinkedinPage)
        ->and($account->external_id)->toBe('111')
        ->and($account->name)->toBe('CodeFanz')
        ->and($account->status)->toBe(SocialAccountStatus::Connected);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'organizationAcls') && $request->hasHeader('LinkedIn-Version') && $request->hasHeader('Authorization', 'Bearer li-access'));
});

test('a linkedin app without community management approval is reported honestly', function () {
    configureSocialApp('linkedin');
    ['state' => $state] = startSocialConnect($this, $this->manager, 'linkedin');

    Http::fake([
        'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'li-access', 'expires_in' => 5184000]),
        'api.linkedin.com/rest/organizationAcls*' => Http::response(['message' => 'Not enough permissions to access: organizationAcls', 'status' => 403], 403),
    ]);

    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'linkedin', 'state' => $state, 'code' => 'li-code']))->assertRedirect();

    expect(SocialAccount::query()->count())->toBe(0);
    $this->actingAs($this->manager)->get(route('admin.social.accounts.index'))->assertSee('data-app-status="requires_app_approval"', false);
});

test('meta connects pages and their instagram accounts, flagging missing publish permissions', function () {
    configureSocialApp('meta');
    ['state' => $state] = startSocialConnect($this, $this->manager, 'meta');

    Http::fake([
        'graph.facebook.com/*/oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'short-user-token'])
            ->push(['access_token' => 'long-user-token', 'expires_in' => 5183944]),
        'graph.facebook.com/*/me/permissions*' => Http::response(['data' => [
            ['permission' => 'pages_show_list', 'status' => 'granted'],
            ['permission' => 'pages_manage_posts', 'status' => 'granted'],
            ['permission' => 'instagram_content_publish', 'status' => 'declined'],
        ]]),
        'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [[
            'id' => '5550001', 'name' => 'CodeFanz Page', 'access_token' => 'page-token-1', 'link' => 'https://www.facebook.com/codefanz',
            'instagram_business_account' => ['id' => '1784000', 'username' => 'codefanz'],
        ]]]),
    ]);

    $this->actingAs($this->manager)->get(route('admin.social.oauth.callback', ['provider' => 'meta', 'state' => $state, 'code' => 'meta-code']))->assertRedirect();

    $page = SocialAccount::query()->where('platform', SocialPlatform::FacebookPage)->sole();
    $instagram = SocialAccount::query()->where('platform', SocialPlatform::InstagramBusiness)->sole();

    expect($page->status)->toBe(SocialAccountStatus::Connected)
        ->and($page->access_token)->toBe('page-token-1')
        ->and($page->token_expires_at)->toBeNull()
        ->and($instagram->status)->toBe(SocialAccountStatus::RequiresAppApproval)
        ->and($instagram->meta['page_id'])->toBe('5550001')
        ->and($instagram->canPublishDirectly())->toBeFalse();
});

test('expiring tokens are refreshed by the scheduled job and a refused refresh asks for a reconnect', function () {
    configureSocialApp('x');
    $soon = SocialAccount::factory()->connected(SocialPlatform::X)->create(['token_expires_at' => now()->addMinutes(10), 'refresh_token' => 'old-refresh']);
    $later = SocialAccount::factory()->connected(SocialPlatform::X)->create(['token_expires_at' => now()->addHours(2)]);

    Queue::fake();
    $this->artisan('social:refresh-tokens')->assertSuccessful();
    Queue::assertPushed(RefreshSocialAccountToken::class, fn ($job): bool => $job->accountId === $soon->id);
    Queue::assertNotPushed(RefreshSocialAccountToken::class, fn ($job): bool => $job->accountId === $later->id);

    Http::fake(['api.x.com/2/oauth2/token' => Http::sequence()
        ->push(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 7200])
        ->push(['error' => 'invalid_request', 'error_description' => 'Value passed for the token was invalid.'], 400)]);
    (new RefreshSocialAccountToken($soon->id))->handle(app(SocialTokenRefresher::class));

    $soon->refresh();
    expect($soon->access_token)->toBe('new-access')
        ->and($soon->refresh_token)->toBe('new-refresh')
        ->and($soon->token_expires_at->gt(now()->addHour()))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'old-refresh');

    $soon->forceFill(['token_expires_at' => now()->addMinutes(5)])->save();
    (new RefreshSocialAccountToken($soon->id))->handle(app(SocialTokenRefresher::class));

    $soon->refresh();
    expect($soon->status)->toBe(SocialAccountStatus::TokenExpired)
        ->and($soon->access_token)->toBeNull()
        ->and($soon->refresh_token)->toBeNull();
});

test('manual accounts can be added, switched to and disconnected with an audit trail', function () {
    $this->actingAs($this->manager)->post(route('admin.social.accounts.store'), [
        'platform' => SocialPlatform::Tiktok->value, 'name' => 'CodeFanz TikTok', 'profile_url' => 'https://www.tiktok.com/@codefanz',
    ])->assertRedirect();

    $tiktok = SocialAccount::query()->sole();
    expect($tiktok->status)->toBe(SocialAccountStatus::ManualMode);

    $linkedin = SocialAccount::factory()->connected()->create();
    $this->actingAs($this->manager)->post(route('admin.social.accounts.manual', $linkedin))->assertRedirect();
    $linkedin->refresh();
    expect($linkedin->status)->toBe(SocialAccountStatus::ManualMode)->and($linkedin->access_token)->toBeNull();

    $this->actingAs($this->manager)->delete(route('admin.social.accounts.destroy', $linkedin))->assertRedirect();
    expect(SocialAccount::query()->find($linkedin->id))->toBeNull()
        ->and(AuditLog::query()->whereIn('operation', ['social_account.manual_added', 'social_account.manual_mode', 'social_account.disconnected'])->count())->toBe(3);
});
