<?php

namespace App\Http\Middleware;

use App\Enums\KillSwitchKey;
use App\Enums\McpCallResult;
use App\Enums\McpScope;
use App\Models\McpClient;
use App\Models\McpToolCall;
use App\Services\KillSwitches;
use App\Services\Mcp\McpArgumentSanitizer;
use App\Services\Mcp\McpErrors;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Runs after auth:sanctum on every /api/v1/mcp route. Resolves the MCP client from the token and refuses the call
 * unless: the global MCP kill switch is off, the client is active and not expired, the caller's IP is allowed, the
 * client's per-minute rate limit is not exceeded, the token carries the endpoint's scope, the owner still holds
 * the scope's permission, and (for writes) the MCP writes kill switch is off. Every call, allowed or denied, is
 * written to mcp_tool_calls with sanitized arguments.
 */
class AuthorizeMcpCall
{
    public const CHANGE_REQUEST_ATTRIBUTE = 'mcp_change_request_id';

    public function __construct(private KillSwitches $killSwitches, private McpArgumentSanitizer $sanitizer) {}

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $startedAt = microtime(true);
        $client = $request->user();

        if (! $client instanceof McpClient) {
            return McpErrors::response('unauthenticated', 'A valid MCP client token is required.', 401);
        }

        $requiredScope = $scope !== null ? McpScope::from($scope) : null;
        $response = $this->deny($request, $client, $requiredScope);

        if ($response === null) {
            $client->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
            $response = $next($request);
        }

        $this->log($request, $client, $response, $startedAt);

        return $response;
    }

    private function deny(Request $request, McpClient $client, ?McpScope $scope): ?JsonResponse
    {
        if ($this->killSwitches->isActive(KillSwitchKey::McpAccess)) {
            return McpErrors::response('mcp_disabled', 'MCP access is paused by an administrator (emergency control).', 503);
        }

        if ($client->isExpired()) {
            return McpErrors::response('client_expired', 'This MCP client has expired. Ask an administrator for a new token.', 401);
        }

        if (! $client->isActive()) {
            return McpErrors::response('client_disabled', 'This MCP client is disabled.', 403);
        }

        if (! empty($client->allowed_ips) && ! IpUtils::checkIp((string) $request->ip(), $client->allowed_ips)) {
            return McpErrors::response('ip_not_allowed', 'Calls from this IP address are not allowed for this client.', 403);
        }

        $key = 'mcp-client:'.$client->getKey();

        if (RateLimiter::tooManyAttempts($key, max(1, $client->rate_limit_per_minute))) {
            $retryAfter = RateLimiter::availableIn($key);

            return McpErrors::response('rate_limited', "Rate limit reached. Retry in {$retryAfter} seconds.", 429, ['retry_after' => $retryAfter], ['Retry-After' => (string) $retryAfter]);
        }

        RateLimiter::hit($key, 60);

        if ($scope === null) {
            return null;
        }

        if (! $client->hasScope($scope) || ! $client->tokenCan($scope->value)) {
            return McpErrors::response('insufficient_scope', "This tool needs the {$scope->value} scope, which the token does not have.", 403, ['required_scope' => $scope->value]);
        }

        $owner = $client->owner;

        if ($owner === null || ! $owner->is_active || ! $owner->can($scope->ownerPermission()->value)) {
            return McpErrors::response('owner_not_permitted', "The client's owner no longer has the permission this tool needs.", 403);
        }

        if ($scope->isWrite() && $this->killSwitches->isActive(KillSwitchKey::McpWrites)) {
            return McpErrors::response('mcp_writes_disabled', 'MCP write operations are paused by an administrator. Read-only tools still work.', 503);
        }

        return null;
    }

    private function log(Request $request, McpClient $client, Response $response, float $startedAt): void
    {
        try {
            $route = $request->route();
            $tool = Str::after((string) $route?->getName(), 'api.mcp.');
            $error = null;

            if ($response->getStatusCode() >= 400 && $response instanceof JsonResponse) {
                $error = data_get($response->getData(true), 'error.code');
            }

            McpToolCall::create([
                'mcp_client_id' => $client->getKey(),
                'tool' => mb_substr($tool ?: 'unknown', 0, 80),
                'method' => $request->method(),
                'arguments' => $this->sanitizer->sanitize([
                    ...($route?->originalParameters() ?? []),
                    ...$request->all(),
                ], $tool) ?: null,
                'result' => McpCallResult::fromStatus($response->getStatusCode()),
                'http_status' => $response->getStatusCode(),
                'error_code' => is_string($error) ? mb_substr($error, 0, 60) : null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'change_request_id' => $request->attributes->get(self::CHANGE_REQUEST_ATTRIBUTE),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
