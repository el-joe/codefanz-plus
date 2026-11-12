<?php

namespace App\Services\Analytics;

use App\Exceptions\GoogleApiException;
use App\Services\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google API access with a service account (JSON key saved encrypted in Settings → Integrations): an RS256 JWT
 * signed with openssl_sign is exchanged for an OAuth access token (JWT bearer grant), cached until shortly before
 * it expires. No Google SDK; the key never leaves the server and is never logged.
 */
class GoogleServiceAccount
{
    public const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public const SCOPE_ANALYTICS = 'https://www.googleapis.com/auth/analytics.readonly';

    public const SCOPE_SEARCH_CONSOLE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public function __construct(private Settings $settings) {}

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * The service account's email (shown so the owner knows which account to grant access to).
     */
    public function clientEmail(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri: string}|null
     */
    public function credentials(): ?array
    {
        $raw = $this->settings->get('integrations', 'google_service_account_json');
        $json = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($json) || ! is_string($json['client_email'] ?? null) || ! is_string($json['private_key'] ?? null)
            || ! str_contains($json['private_key'], 'PRIVATE KEY')) {
            return null;
        }

        $tokenUri = is_string($json['token_uri'] ?? null) && str_starts_with($json['token_uri'], 'https://') ? $json['token_uri'] : self::TOKEN_URI;

        return ['client_email' => $json['client_email'], 'private_key' => $json['private_key'], 'token_uri' => $tokenUri];
    }

    /**
     * A signed JWT assertion for the given scopes.
     *
     * @param  list<string>  $scopes
     *
     * @throws GoogleApiException
     */
    public function assertion(array $scopes, ?int $issuedAt = null): string
    {
        $credentials = $this->credentials() ?? throw GoogleApiException::notConfigured();
        $issuedAt ??= now()->getTimestamp();

        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => implode(' ', $scopes),
                'aud' => $credentials['token_uri'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];

        $key = openssl_pkey_get_private($credentials['private_key']);

        if ($key === false || ! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw GoogleApiException::invalidCredentials('The service account private key could not be read.');
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    /**
     * @param  list<string>  $scopes
     *
     * @throws GoogleApiException
     */
    public function accessToken(array $scopes): string
    {
        $credentials = $this->credentials() ?? throw GoogleApiException::notConfigured();
        $cacheKey = 'google:token:'.sha1($credentials['client_email'].'|'.implode(' ', $scopes));
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->acceptJson()->timeout(10)->connectTimeout(5)->post($credentials['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->assertion($scopes),
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            $error = (string) ($response->json('error') ?? 'http_'.$response->status());

            throw GoogleApiException::invalidCredentials(__('Google rejected the service account (:error).', ['error' => mb_substr($error, 0, 60)]));
        }

        $ttl = max(60, (int) ($response->json('expires_in') ?? 3600) - 120);
        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    public function forgetTokens(): void
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return;
        }

        foreach ([[self::SCOPE_ANALYTICS], [self::SCOPE_SEARCH_CONSOLE]] as $scopes) {
            Cache::forget('google:token:'.sha1($credentials['client_email'].'|'.implode(' ', $scopes)));
        }
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
