<?php

namespace App\Services\Mcp;

use App\Exceptions\GovernanceException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The single error envelope of /api/v1/mcp: {"error": {"code", "message", "details"?}}. Messages are safe for an
 * AI client to read: they never contain secrets, stack traces or SQL.
 */
class McpErrors
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function response(string $code, string $message, int $status, array $details = [], array $headers = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }

    public static function fromThrowable(Throwable $exception): JsonResponse
    {
        return match (true) {
            $exception instanceof AuthenticationException => self::response('unauthenticated', 'A valid MCP client token is required.', 401),
            $exception instanceof ValidationException => self::response('validation_failed', 'The input is invalid.', 422, ['fields' => $exception->errors()]),
            $exception instanceof ModelNotFoundException => self::response('not_found', 'The requested item does not exist or is not visible to this client.', 404),
            $exception instanceof AuthorizationException => self::response('forbidden', $exception->getMessage() ?: 'This action is not allowed.', 403),
            $exception instanceof GovernanceException => self::response($exception->reason, $exception->getMessage(), 409, ['retryable' => $exception->retryable]),
            $exception instanceof ThrottleRequestsException => self::response('rate_limited', 'Too many requests.', 429, headers: $exception->getHeaders()),
            $exception instanceof HttpExceptionInterface => self::response(
                match ($exception->getStatusCode()) {
                    403 => 'forbidden',
                    404 => 'not_found',
                    405 => 'method_not_allowed',
                    default => 'http_error',
                },
                $exception->getStatusCode() === 404 ? 'The requested item does not exist or is not visible to this client.' : ($exception->getMessage() ?: 'Request failed.'),
                $exception->getStatusCode(),
                headers: $exception->getHeaders(),
            ),
            default => self::response('server_error', 'The server could not complete this call.', 500),
        };
    }
}
