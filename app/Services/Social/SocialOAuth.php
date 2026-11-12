<?php

namespace App\Services\Social;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Exceptions\SocialApiException;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * OAuth 2.0 authorization-code flows for LinkedIn (Community Management API), Meta (Facebook Login for Pages +
 * Instagram via the page) and X (OAuth 2.0 with PKCE). A random state (and PKCE verifier where supported) is kept
 * in the staff member's session for a few minutes and must match exactly once. Tokens are stored encrypted on the
 * accounts; nothing secret is logged or sent to the browser.
 */
class SocialOAuth
{
    private const SESSION_KEY = 'social_oauth';

    public function __construct(private SocialApps $apps) {}

    public function authorizationUrl(string $provider, User $user): string
    {
        if (! $this->apps->isConfigured($provider)) {
            throw ValidationException::withMessages(['provider' => __('Save the :app credentials in Settings → Social apps first.', ['app' => $this->apps->label($provider)])]);
        }

        [$clientId] = $this->apps->credentials($provider);
        $state = Str::random(40);
        $pending = ['state' => $state, 'user_id' => $user->getKey(), 'expires_at' => now()->addMinutes((int) config('codefanz.social.oauth_state_minutes', 10))->getTimestamp()];
        $query = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->apps->redirectUri($provider),
            'state' => $state,
            'scope' => implode($provider === 'meta' ? ',' : ' ', $this->apps->scopes($provider)),
        ];

        if ($this->apps->usesPkce($provider)) {
            $pending['verifier'] = Str::random(64);
            $query['code_challenge'] = self::challenge($pending['verifier']);
            $query['code_challenge_method'] = 'S256';
        }

        session()->put(self::SESSION_KEY.'.'.$provider, $pending);

        return config("codefanz.social.providers.{$provider}.authorize_url").'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Validate the state (one use, same user, not expired) and return the pending PKCE verifier, if any.
     *
     * @throws ValidationException
     */
    public function consumeState(string $provider, ?string $state, User $user): ?string
    {
        $pending = session()->pull(self::SESSION_KEY.'.'.$provider);

        $valid = is_array($pending)
            && is_string($state) && $state !== ''
            && hash_equals((string) ($pending['state'] ?? ''), $state)
            && (int) ($pending['user_id'] ?? 0) === (int) $user->getKey()
            && (int) ($pending['expires_at'] ?? 0) >= now()->getTimestamp();

        if (! $valid) {
            throw ValidationException::withMessages(['state' => __('The connection request expired or did not come from this session. Start again from the accounts page.')]);
        }

        return isset($pending['verifier']) ? (string) $pending['verifier'] : null;
    }

    /**
     * Exchange the code and create or refresh the accounts it grants access to.
     *
     * @return Collection<int, SocialAccount>
     *
     * @throws SocialApiException
     */
    public function complete(string $provider, string $code, ?string $verifier, User $user): Collection
    {
        return match ($provider) {
            'linkedin' => $this->completeLinkedIn($code, $user),
            'meta' => $this->completeMeta($code, $user),
            'x' => $this->completeX($code, (string) $verifier, $user),
            default => throw new SocialApiException(__('Unknown social app.')),
        };
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    private function completeLinkedIn(string $code, User $user): Collection
    {
        [$clientId, $secret] = $this->apps->credentials('linkedin');
        $token = $this->send(fn () => $this->http()->asForm()->post((string) config('codefanz.social.providers.linkedin.token_url'), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->apps->redirectUri('linkedin'),
            'client_id' => $clientId,
            'client_secret' => $secret,
        ]), __('LinkedIn token exchange'))->json();

        $accessToken = (string) ($token['access_token'] ?? '');
        $api = $this->http()->withToken($accessToken)->withHeaders([
            'LinkedIn-Version' => (string) config('codefanz.social.providers.linkedin.version'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ]);
        $base = rtrim((string) config('codefanz.social.providers.linkedin.api_url'), '/');

        try {
            $acls = $this->send(fn () => $api->get($base.'/rest/organizationAcls', ['q' => 'roleAssignee', 'role' => 'ADMINISTRATOR', 'state' => 'APPROVED']), __('LinkedIn page list'))->json('elements', []);
        } catch (SocialApiException $exception) {
            if ($exception->kind === SocialApiException::APP_APPROVAL) {
                $this->apps->recordOutcome('linkedin', SocialAccountStatus::RequiresAppApproval, __('LinkedIn has not approved the Community Management API for this app yet.'));
            }

            throw $exception;
        }

        $accounts = collect($acls)
            ->map(fn ($acl): string => (string) Str::after((string) data_get($acl, 'organization', ''), 'urn:li:organization:'))
            ->filter(fn (string $id): bool => preg_match('/^\d+$/', $id) === 1)
            ->unique()
            ->map(function (string $id) use ($api, $base, $token, $accessToken, $user): SocialAccount {
                $name = __('LinkedIn page :id', ['id' => $id]);
                $vanity = null;

                try {
                    $organization = $this->send(fn () => $api->get($base.'/rest/organizations/'.$id), __('LinkedIn page'))->json();
                    $name = (string) ($organization['localizedName'] ?? $name);
                    $vanity = $organization['vanityName'] ?? null;
                } catch (SocialApiException) {
                    // The page name is cosmetic; the account still works.
                }

                return $this->upsert(SocialPlatform::LinkedinPage, $id, [
                    'name' => mb_substr($name, 0, 150),
                    'handle' => is_string($vanity) ? $vanity : null,
                    'profile_url' => is_string($vanity) ? 'https://www.linkedin.com/company/'.$vanity.'/' : null,
                    'access_token' => $accessToken,
                    'refresh_token' => $token['refresh_token'] ?? null,
                    'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
                    'refresh_expires_at' => isset($token['refresh_token_expires_in']) ? now()->addSeconds((int) $token['refresh_token_expires_in']) : null,
                    'scopes' => array_values(array_filter(explode(',', str_replace(' ', ',', (string) ($token['scope'] ?? ''))))),
                    'status' => SocialAccountStatus::Connected,
                ], $user);
            })
            ->values();

        $this->apps->recordOutcome('linkedin', $accounts->isEmpty() ? SocialAccountStatus::Disconnected : SocialAccountStatus::Connected, $accounts->isEmpty() ? __('No LinkedIn pages where you are an administrator.') : null);

        return $accounts;
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    private function completeMeta(string $code, User $user): Collection
    {
        [$appId, $secret] = $this->apps->credentials('meta');
        $graph = rtrim((string) config('codefanz.social.providers.meta.graph_url'), '/').'/'.config('codefanz.social.providers.meta.version');

        $short = (string) $this->send(fn () => $this->http()->get($graph.'/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $secret,
            'redirect_uri' => $this->apps->redirectUri('meta'),
            'code' => $code,
        ]), __('Meta token exchange'))->json('access_token');

        $userToken = (string) $this->send(fn () => $this->http()->get($graph.'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $secret,
            'fb_exchange_token' => $short,
        ]), __('Meta long-lived token'))->json('access_token');

        $granted = collect($this->send(fn () => $this->http()->get($graph.'/me/permissions', ['access_token' => $userToken]), __('Meta permissions'))->json('data', []))
            ->where('status', 'granted')
            ->pluck('permission')
            ->all();

        $pages = $this->send(fn () => $this->http()->get($graph.'/me/accounts', [
            'fields' => 'id,name,link,access_token,instagram_business_account{id,username}',
            'limit' => 100,
            'access_token' => $userToken,
        ]), __('Facebook page list'))->json('data', []);

        $pageStatus = in_array('pages_manage_posts', $granted, true) ? SocialAccountStatus::Connected : SocialAccountStatus::RequiresAppApproval;
        $igStatus = in_array('instagram_content_publish', $granted, true) ? SocialAccountStatus::Connected : SocialAccountStatus::RequiresAppApproval;
        $accounts = collect();

        foreach ($pages as $page) {
            if (! is_array($page) || blank($page['id'] ?? null) || blank($page['access_token'] ?? null)) {
                continue;
            }

            // Page tokens obtained from a long-lived user token do not expire.
            $accounts->push($this->upsert(SocialPlatform::FacebookPage, (string) $page['id'], [
                'name' => mb_substr((string) ($page['name'] ?? $page['id']), 0, 150),
                'profile_url' => is_string($page['link'] ?? null) ? $page['link'] : 'https://www.facebook.com/'.$page['id'],
                'access_token' => (string) $page['access_token'],
                'refresh_token' => null,
                'token_expires_at' => null,
                'scopes' => $granted,
                'status' => $pageStatus,
            ], $user));

            $instagram = $page['instagram_business_account'] ?? null;

            if (is_array($instagram) && filled($instagram['id'] ?? null)) {
                $username = (string) ($instagram['username'] ?? '');
                $accounts->push($this->upsert(SocialPlatform::InstagramBusiness, (string) $instagram['id'], [
                    'name' => $username !== '' ? '@'.$username : (string) ($page['name'] ?? $instagram['id']),
                    'handle' => $username !== '' ? $username : null,
                    'profile_url' => $username !== '' ? 'https://www.instagram.com/'.$username.'/' : null,
                    'access_token' => (string) $page['access_token'],
                    'refresh_token' => null,
                    'token_expires_at' => null,
                    'scopes' => $granted,
                    'meta' => ['page_id' => (string) $page['id']],
                    'status' => $igStatus,
                ], $user));
            }
        }

        $this->apps->recordOutcome('meta', match (true) {
            $accounts->isEmpty() => SocialAccountStatus::Disconnected,
            $pageStatus !== SocialAccountStatus::Connected || $igStatus !== SocialAccountStatus::Connected => SocialAccountStatus::RequiresAppApproval,
            default => SocialAccountStatus::Connected,
        }, $accounts->isEmpty() ? __('No Facebook pages were shared with the app.') : null);

        return $accounts;
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    private function completeX(string $code, string $verifier, User $user): Collection
    {
        [$clientId, $secret] = $this->apps->credentials('x');
        $base = rtrim((string) config('codefanz.social.providers.x.api_url'), '/');

        $token = $this->send(fn () => $this->http()->asForm()->withBasicAuth($clientId, $secret)->post($base.'/2/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->apps->redirectUri('x'),
            'code_verifier' => $verifier,
            'client_id' => $clientId,
        ]), __('X token exchange'))->json();

        $accessToken = (string) ($token['access_token'] ?? '');

        try {
            $me = $this->send(fn () => $this->http()->withToken($accessToken)->get($base.'/2/users/me'), __('X profile'))->json('data', []);
        } catch (SocialApiException $exception) {
            if ($exception->kind === SocialApiException::APP_APPROVAL) {
                $this->apps->recordOutcome('x', SocialAccountStatus::RequiresAppApproval, __('The X app has no API access level that allows this yet.'));
            }

            throw $exception;
        }

        $username = (string) ($me['username'] ?? '');
        $account = $this->upsert(SocialPlatform::X, (string) ($me['id'] ?? ''), [
            'name' => mb_substr((string) ($me['name'] ?? $username), 0, 150),
            'handle' => $username !== '' ? $username : null,
            'profile_url' => $username !== '' ? 'https://x.com/'.$username : null,
            'access_token' => $accessToken,
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'scopes' => array_values(array_filter(explode(' ', (string) ($token['scope'] ?? '')))),
            'status' => SocialAccountStatus::Connected,
        ], $user);

        $this->apps->recordOutcome('x', SocialAccountStatus::Connected);

        return collect([$account]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsert(SocialPlatform $platform, string $externalId, array $attributes, User $user): SocialAccount
    {
        if ($externalId === '') {
            throw new SocialApiException(__('The platform returned no account id.'));
        }

        $account = SocialAccount::withTrashed()->firstOrNew(['platform' => $platform, 'external_id' => $externalId]);

        if ($account->trashed()) {
            $account->restore();
        }

        $account->forceFill([
            ...$attributes,
            'last_verified_at' => now(),
            'last_error' => null,
            'connected_by' => $user->getKey(),
        ])->save();

        return $account;
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('codefanz.social.timeout_seconds', 30))
            ->connectTimeout((int) config('codefanz.social.connect_timeout_seconds', 5))
            ->acceptJson();
    }

    /**
     * @param  callable(): Response  $call
     */
    private function send(callable $call, string $what): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $exception) {
            throw HttpSocialPublisher::connectionFailure($exception, $what);
        }

        if ($response->failed()) {
            $status = $response->status();
            $detail = (string) ($response->json('error_description') ?? $response->json('error.message') ?? $response->json('message') ?? $response->json('detail') ?? '');

            throw new SocialApiException(trim($what.' (HTTP '.$status.')'.($detail !== '' ? ': '.mb_substr($detail, 0, 200) : '')), match (true) {
                $status === 429, $status >= 500 => SocialApiException::TRANSIENT,
                $status === 401 => SocialApiException::AUTH,
                $status === 403 => SocialApiException::APP_APPROVAL,
                default => SocialApiException::PERMANENT,
            }, $status);
        }

        return $response;
    }
}
