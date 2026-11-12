<?php

namespace App\Http\Middleware;

use App\Services\CurrentCustomer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client portal gate. Staff are sent to the admin; clients need an active membership in an active customer.
 * Resolves the current customer (see App\Services\CurrentCustomer) on every request.
 */
class EnsurePortalAccess
{
    public function __construct(private CurrentCustomer $currentCustomer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        abort_if(
            $this->currentCustomer->resolve($user, $request->session()) === null,
            403,
            __('Your client portal access is not active. Please contact CodeFanz.'),
        );

        return $next($request);
    }
}
