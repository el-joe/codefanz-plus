<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Privileged roles must confirm two-factor authentication before using the admin area.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->requiresTwoFactor() && ! $user->hasConfirmedTwoFactor()) {
            return redirect()->route('admin.security')
                ->with('status', 'two-factor-required');
        }

        return $next($request);
    }
}
