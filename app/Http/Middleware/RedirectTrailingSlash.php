<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/en/` → 301 `/en` (query string kept), mirroring the Apache `.htaccess` rule and the Nginx config so every server
 * (including `php artisan serve`) has one canonical URL per page. GET/HEAD only; the root path is left alone.
 */
class RedirectTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if ($path !== '/' && str_ends_with($path, '/') && $request->isMethodSafe()) {
            $target = '/'.trim($path, '/');
            $query = $request->getQueryString();

            // Absolute URL on the current host: a path like `//evil.example/` can never become an off-site redirect.
            return redirect()->to($request->getSchemeAndHttpHost().$request->getBaseUrl().$target.($query !== null ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
