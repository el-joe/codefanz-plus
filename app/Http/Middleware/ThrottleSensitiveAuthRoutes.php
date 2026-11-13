<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify only throttles login and the two-factor challenge. This adds the `auth-sensitive` limiter to the other
 * credential endpoints it registers (reset link requests, password resets, password confirmation and change, 2FA
 * confirmation), which would otherwise allow unlimited guessing or mail flooding. Runs in the web group, after
 * routing, so the route name is known.
 */
class ThrottleSensitiveAuthRoutes
{
    /**
     * @var list<string>
     */
    public const ROUTES = [
        'password.email',
        'password.update',
        'password.confirm.store',
        'user-password.update',
        'two-factor.confirm',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->route()?->getName(), self::ROUTES, true)) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'auth-sensitive');
    }
}
