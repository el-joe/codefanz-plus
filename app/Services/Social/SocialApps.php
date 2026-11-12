<?php

namespace App\Services\Social;

use App\Enums\SocialAccountStatus;
use App\Services\Settings;
use Carbon\CarbonImmutable;

/**
 * The OAuth apps (LinkedIn, Meta, X) social accounts connect through. Client ids and secrets live in Settings →
 * Social apps (secrets encrypted). The last connection outcome per app is kept in Settings (social_state) so the
 * accounts page can say "needs app approval" even when no account could be created.
 */
class SocialApps
{
    public const STATE_GROUP = 'social_state';

    public function __construct(private Settings $settings) {}

    /**
     * @return list<string>
     */
    public function providers(): array
    {
        return array_keys((array) config('codefanz.social.providers'));
    }

    public function exists(string $provider): bool
    {
        return in_array($provider, $this->providers(), true);
    }

    public function label(string $provider): string
    {
        return (string) config("codefanz.social.providers.{$provider}.label", $provider);
    }

    public function isConfigured(string $provider): bool
    {
        $keys = (array) config("codefanz.social.providers.{$provider}.credentials", []);

        return $keys !== [] && collect($keys)->every(fn (string $key): bool => $this->settings->has('social', $key));
    }

    /**
     * @return array{0: string, 1: string} client id and secret
     */
    public function credentials(string $provider): array
    {
        [$idKey, $secretKey] = array_pad((array) config("codefanz.social.providers.{$provider}.credentials", []), 2, '');

        return [(string) $this->settings->get('social', $idKey, ''), (string) $this->settings->get('social', $secretKey, '')];
    }

    public function redirectUri(string $provider): string
    {
        return route('admin.social.oauth.callback', $provider);
    }

    /**
     * @return list<string>
     */
    public function scopes(string $provider): array
    {
        return (array) config("codefanz.social.providers.{$provider}.scopes", []);
    }

    public function usesPkce(string $provider): bool
    {
        return (bool) config("codefanz.social.providers.{$provider}.pkce", false);
    }

    /**
     * App-level status shown on the accounts page.
     */
    public function status(string $provider): SocialAccountStatus
    {
        if (! $this->isConfigured($provider)) {
            return SocialAccountStatus::RequiresCredentials;
        }

        $state = $this->lastOutcome($provider);

        return SocialAccountStatus::tryFrom((string) ($state['status'] ?? '')) ?? SocialAccountStatus::Disconnected;
    }

    /**
     * @return array{status?: string, message?: string|null, at?: string}|null
     */
    public function lastOutcome(string $provider): ?array
    {
        $state = $this->settings->get(self::STATE_GROUP, $provider);

        return is_array($state) ? $state : null;
    }

    public function recordOutcome(string $provider, SocialAccountStatus $status, ?string $message = null): void
    {
        $this->settings->set(self::STATE_GROUP, $provider, [
            'status' => $status->value,
            'message' => $message !== null ? mb_substr($message, 0, 300) : null,
            'at' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }
}
