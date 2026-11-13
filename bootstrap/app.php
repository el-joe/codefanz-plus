<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\InboundAutomationController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialMediaController;
use App\Http\Middleware\AddRobotsHeader;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthorizeMcpCall;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\RedirectTrailingSlash;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleFromPreference;
use App\Http\Middleware\SetLocaleFromRoute;
use App\Http\Middleware\ThrottleSensitiveAuthRoutes;
use App\Services\Analytics\AnalyticsConsent;
use App\Services\Mcp\McpErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Uptime monitors and the deploy script (M12): JSON status, details for allow-listed IPs / token only.
            Route::get('health', HealthController::class)->name('health');

            // Crawler files: no session or cookies. robots.txt replaces the old static file.
            Route::get('robots.txt', RobotsController::class)->name('robots');
            Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
            Route::get('sitemaps/{name}.xml', [SitemapController::class, 'show'])->where('name', '[a-z]+-[a-z]{2}')->name('sitemap.child');

            // Gateway webhooks: no session or CSRF; the driver verifies the gateway's signature.
            Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
                ->where('gateway', '[a-z0-9_-]+')
                ->middleware('throttle:webhooks')
                ->name('webhooks.payments');

            // n8n inbound endpoints: no session or CSRF; HMAC signature, timestamp and delivery id are checked first.
            Route::post('webhooks/n8n/{key}', InboundAutomationController::class)
                ->where('key', '[a-z0-9-]+')
                ->middleware('throttle:webhooks')
                ->name('webhooks.n8n');

            // Social images fetched by Meta (Facebook / Instagram) while publishing: signed, expiring, no session.
            Route::get('social-media/{media}', SocialMediaController::class)
                ->whereUuid('media')
                ->middleware(['signed', 'throttle:120,1'])
                ->name('social.media');

            Route::middleware(['web', 'auth', 'active', 'locale.preference', 'can:admin.access'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', 'auth', 'active', 'locale.preference', 'portal'])
                ->prefix('portal')
                ->name('portal.')
                ->group(base_path('routes/portal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        // Managed redirects answer requests that would otherwise 404; staging responses carry X-Robots-Tag.
        // SecurityHeaders: CSP (nonce shared with Vite), HSTS on https, nosniff, frame and referrer policies (M11).
        // RedirectTrailingSlash: `/en/` → 301 `/en`, matching the Apache and Nginx rules (M12); runs after TrustProxies.
        $middleware->append([RedirectTrailingSlash::class, HandleRedirects::class, AddRobotsHeader::class, SecurityHeaders::class]);
        // The consent cookie (written by the banner script) and GA4's own _ga cookie are read as plain text.
        // Rate limits for Fortify's credential routes that Fortify itself does not throttle (M11).
        $middleware->web(append: [ThrottleSensitiveAuthRoutes::class]);
        $middleware->encryptCookies(except: [AnalyticsConsent::COOKIE, '_ga']);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'two-factor' => RequireTwoFactor::class,
            'portal' => EnsurePortalAccess::class,
            'locale.route' => SetLocaleFromRoute::class,
            'locale.preference' => SetLocaleFromPreference::class,
            'mcp' => AuthorizeMcpCall::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // MCP API: one error envelope for every failure (validation, auth, governance, not found, server errors).
        $exceptions->render(fn (Throwable $exception, Request $request) => $request->is('api/v1/mcp', 'api/v1/mcp/*')
            ? McpErrors::fromThrowable($exception)
            : null);
    })->create();
