<?php

use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialMediaController;
use App\Http\Middleware\AddRobotsHeader;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SetLocaleFromPreference;
use App\Http\Middleware\SetLocaleFromRoute;
use App\Services\Analytics\AnalyticsConsent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Crawler files: no session or cookies. robots.txt replaces the old static file.
            Route::get('robots.txt', RobotsController::class)->name('robots');
            Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
            Route::get('sitemaps/{name}.xml', [SitemapController::class, 'show'])->where('name', '[a-z]+-[a-z]{2}')->name('sitemap.child');

            // Gateway webhooks: no session or CSRF; the driver verifies the gateway's signature.
            Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
                ->where('gateway', '[a-z0-9_-]+')
                ->middleware('throttle:webhooks')
                ->name('webhooks.payments');

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
        $middleware->append([HandleRedirects::class, AddRobotsHeader::class]);
        // The consent cookie (written by the banner script) and GA4's own _ga cookie are read as plain text.
        $middleware->encryptCookies(except: [AnalyticsConsent::COOKIE, '_ga']);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'two-factor' => RequireTwoFactor::class,
            'portal' => EnsurePortalAccess::class,
            'locale.route' => SetLocaleFromRoute::class,
            'locale.preference' => SetLocaleFromPreference::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
