<?php

namespace App\Http\Middleware;

use App\Services\Seo\RedirectMap;
use App\Services\Seo\RedirectPaths;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Managed redirects are consulted only when the request would otherwise end in a 404, so a live page always wins
 * over a stale redirect. 301/302 redirect (keeping the query string when the target has none); 410 answers Gone.
 */
class HandleRedirects
{
    public function __construct(private RedirectMap $map) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $response;
        }

        $path = RedirectPaths::source($request->getPathInfo());
        $redirect = $path === null ? null : $this->map->find($path);

        if ($redirect === null) {
            return $response;
        }

        DB::table('redirects')->where('id', $redirect['id'])->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);

        if ($redirect['code'] === 410 || $redirect['to'] === null) {
            return response()->view('errors.410', [], 410);
        }

        $target = $redirect['to'];
        $query = $request->getQueryString();

        if ($query !== null && ! str_contains($target, '?')) {
            $target .= '?'.$query;
        }

        return redirect()->to(RedirectPaths::isExternal($target) ? $target : url(RedirectPaths::encode($target)), $redirect['code']);
    }
}
