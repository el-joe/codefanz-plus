<?php

namespace App\Http\Middleware;

use App\Services\Analytics\AnalyticsConsent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every response, and a nonce-based Content-Security-Policy on HTML pages.
 *
 * The nonce is shared with Vite (`Vite::useCspNonce()`), so @vite/@fonts tags and inline scripts that print
 * `nonce="{{ Vite::cspNonce() }}"` are allowed; anything else inline (injected markup, event handlers) is blocked.
 * JSON-LD blocks are data, not script, and need no nonce. Debug exception pages are left without a CSP.
 */
class SecurityHeaders
{
    public function __construct(private AnalyticsConsent $consent) {}

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && config('codefanz.security.hsts_max_age') > 0) {
            $headers->set('Strict-Transport-Security', 'max-age='.(int) config('codefanz.security.hsts_max_age').'; includeSubDomains');
        }

        if ($this->wantsPolicy($response)) {
            $headers->set(
                config('codefanz.security.csp.report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
                $this->policy($request, $nonce),
            );
        }

        return $response;
    }

    private function wantsPolicy(Response $response): bool
    {
        if (! config('codefanz.security.csp.enabled')) {
            return false;
        }

        // Ignition / Symfony debug pages use their own inline scripts.
        if (config('app.debug') && property_exists($response, 'exception') && $response->exception !== null) {
            return false;
        }

        return str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html');
    }

    private function policy(Request $request, string $nonce): string
    {
        $csp = config('codefanz.security.csp');
        $analytics = $this->consent->measurementId() !== null ? $csp['analytics'] : ['script_src' => [], 'connect_src' => [], 'img_src' => []];
        $dev = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$analytics['script_src'], ...$dev],
            'style-src' => ["'self'", "'nonce-{$nonce}'", ...$dev],
            // Inline style attributes (rich-text editor, progress bars) cannot carry a nonce.
            'style-src-attr' => ["'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:', ...$csp['img_src'], ...$analytics['img_src'], ...$dev],
            'font-src' => ["'self'", 'data:', ...$dev],
            'connect-src' => ["'self'", ...$csp['connect_src'], ...$analytics['connect_src'], ...$dev, ...array_map(fn (string $origin): string => preg_replace('#^http#', 'ws', $origin), $dev)],
            'frame-src' => $csp['frame_src'],
            'media-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            // Checkout and social sign-in forms post here and are redirected to the provider over https.
            'form-action' => ["'self'", 'https:'],
            'frame-ancestors' => ["'none'"],
        ];

        $policy = collect($directives)->map(fn (array $sources, string $name): string => trim($name.' '.implode(' ', array_unique($sources))))->implode('; ');

        return $request->isSecure() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /**
     * The Vite dev server origin while `npm run dev` is running, so local pages keep hot reload.
     *
     * @return list<string>
     */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return [];
        }

        $url = trim((string) @file_get_contents(Vite::hotFile()));
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host'])
            ? [$parts['scheme'].'://'.(str_contains($parts['host'], ':') && ! str_starts_with($parts['host'], '[') ? '['.$parts['host'].']' : $parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '')]
            : [];
    }
}
