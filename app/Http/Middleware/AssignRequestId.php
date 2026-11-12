<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a UUID that is added to the log context (so exception reports carry it), returned in the
 * X-Request-Id header and shown on error pages as the error reference. Client-supplied ids are ignored.
 */
class AssignRequestId
{
    public const CONTEXT_KEY = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = self::current();

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }

    /**
     * The current request id, created on first use (error pages may render before the middleware ran).
     */
    public static function current(): string
    {
        $id = Context::get(self::CONTEXT_KEY);

        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid();
            Context::add(self::CONTEXT_KEY, $id);
        }

        return $id;
    }
}
