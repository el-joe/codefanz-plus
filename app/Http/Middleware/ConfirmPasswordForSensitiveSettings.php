<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings groups holding financial credentials (Payments) require a recent password confirmation, like the
 * `password.confirm` middleware; other settings groups pass through.
 */
class ConfirmPasswordForSensitiveSettings
{
    /**
     * @var list<string>
     */
    public const GROUPS = ['payments'];

    public function __construct(private RequirePassword $requirePassword) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->route('group'), self::GROUPS, true)) {
            return $this->requirePassword->handle($request, $next);
        }

        return $next($request);
    }
}
