<?php

use App\Http\Controllers\PaymentWebhookController;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SetLocaleFromPreference;
use App\Http\Middleware\SetLocaleFromRoute;
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
            // Gateway webhooks: no session or CSRF; the driver verifies the gateway's signature.
            Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
                ->where('gateway', '[a-z0-9_-]+')
                ->middleware('throttle:webhooks')
                ->name('webhooks.payments');

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
