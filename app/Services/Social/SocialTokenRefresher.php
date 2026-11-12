<?php

namespace App\Services\Social;

use App\Enums\ActorType;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Exceptions\SocialApiException;
use App\Models\SocialAccount;
use App\Services\AuditLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes OAuth tokens before they expire (LinkedIn, X). Meta page tokens do not expire and have no refresh
 * token. A refused refresh (revoked app, expired refresh token) drops the tokens and marks the account
 * "reconnect required"; temporary failures are left for the next run.
 */
class SocialTokenRefresher
{
    public function __construct(private SocialApps $apps, private AuditLogger $audit) {}

    public function needsRefresh(SocialAccount $account): bool
    {
        $provider = $account->platform->provider();

        if ($provider === null || blank($account->refresh_token) || $account->token_expires_at === null || ! $account->status->canPublish()) {
            return false;
        }

        $lead = (int) config("codefanz.social.refresh_before_minutes.{$provider}", 30);

        return $account->token_expires_at->lte(now()->addMinutes($lead));
    }

    /**
     * @throws SocialApiException only for transient failures (the caller may retry)
     */
    public function refresh(SocialAccount $account): SocialAccount
    {
        $provider = (string) $account->platform->provider();
        [$clientId, $secret] = $this->apps->credentials($provider);

        if ($clientId === '' || $secret === '' || blank($account->refresh_token)) {
            return $this->expire($account, __('No refresh token or app credentials; reconnect the account.'));
        }

        $oldRefreshToken = (string) $account->refresh_token;

        try {
            $response = match ($provider) {
                'linkedin' => Http::asForm()->acceptJson()->timeout(30)->post((string) config('codefanz.social.providers.linkedin.token_url'), [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $oldRefreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $secret,
                ]),
                'x' => Http::asForm()->acceptJson()->timeout(30)->withBasicAuth($clientId, $secret)->post(rtrim((string) config('codefanz.social.providers.x.api_url'), '/').'/2/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $oldRefreshToken,
                    'client_id' => $clientId,
                ]),
                default => null,
            };
        } catch (ConnectionException $exception) {
            throw HttpSocialPublisher::connectionFailure($exception, __('Token refresh'));
        }

        if ($response === null) {
            return $account;
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new SocialApiException(__('Token refresh (HTTP :status)', ['status' => $response->status()]), SocialApiException::TRANSIENT, $response->status());
        }

        if ($response->failed() || blank($response->json('access_token'))) {
            return $this->expire($account, __('The platform refused to refresh the token (HTTP :status). Reconnect the account.', ['status' => $response->status()]));
        }

        $token = $response->json();
        $values = [
            'access_token' => (string) $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $oldRefreshToken,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'refresh_expires_at' => isset($token['refresh_token_expires_in']) ? now()->addSeconds((int) $token['refresh_token_expires_in']) : $account->refresh_expires_at,
            'last_error' => null,
        ];

        // One LinkedIn member token serves every page it administers: update all accounts sharing it.
        $this->sharingRefreshToken($account, $oldRefreshToken)->each(function (SocialAccount $shared) use ($values): void {
            $shared->forceFill($values)->save();
        });

        $this->audit->record(operation: 'social_account.token_refreshed', auditable: $account, actorType: ActorType::System, actorLabel: 'Scheduler', after: ['platform' => $account->platform->value, 'expires_at' => $values['token_expires_at']?->toIso8601String()]);

        return $account->refresh();
    }

    private function expire(SocialAccount $account, string $message): SocialAccount
    {
        $this->sharingRefreshToken($account, (string) $account->refresh_token)->each(function (SocialAccount $shared) use ($message): void {
            $shared->forgetTokens(SocialAccountStatus::TokenExpired);
            $shared->forceFill(['last_error' => $message])->save();
        });

        $this->audit->record(operation: 'social_account.token_expired', auditable: $account, actorType: ActorType::System, actorLabel: 'Scheduler', after: ['platform' => $account->platform->value], result: 'failure');

        return $account->refresh();
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    private function sharingRefreshToken(SocialAccount $account, string $refreshToken): Collection
    {
        if ($account->platform !== SocialPlatform::LinkedinPage || $refreshToken === '') {
            return collect([$account]);
        }

        return SocialAccount::query()
            ->where('platform', SocialPlatform::LinkedinPage)
            ->get()
            ->filter(fn (SocialAccount $candidate): bool => $candidate->is($account) || hash_equals((string) $candidate->refresh_token, $refreshToken))
            ->values();
    }
}
