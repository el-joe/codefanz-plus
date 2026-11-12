<?php

namespace App\Http\Middleware;

use App\Services\Seo\IndexingPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staging protection: while indexing is blocked every response carries `X-Robots-Tag: noindex, nofollow`.
 */
class AddRobotsHeader
{
    public function __construct(private IndexingPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->policy->blocksIndexing()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
