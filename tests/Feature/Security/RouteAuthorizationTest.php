<?php

use App\Enums\Role;
use App\Http\Middleware\AuthorizeMcpCall;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequireTwoFactor;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| M11 security review: every admin, portal and API route is enumerated from the router, so a route added later
| without the right middleware or authorization fails here.
*/

/**
 * Admin routes that are deliberately limited to the signed-in user's own data (profile, own notifications,
 * 2FA setup, the overview whose sections are permission-checked inside the controller).
 */
const SELF_SERVICE_ADMIN_ROUTES = [
    'admin.dashboard',
    'admin.profile',
    'admin.security',
    'admin.notifications.index',
    'admin.notifications.read',
    'admin.notifications.read-all',
];

/**
 * Admin routes reachable before two-factor confirmation (needed to set it up).
 */
const PRE_TWO_FACTOR_ADMIN_ROUTES = ['admin.security', 'admin.profile', 'admin.profile.author'];

/**
 * Routes of one area, read from a freshly booted application (datasets are built before the test app exists).
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function protectedRoutes(string $prefix): array
{
    static $routes = null;

    if ($routes === null) {
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        // Leave PHP's handlers as the test runner set them (HandleExceptions registered its own while booting).
        restore_error_handler();
        restore_exception_handler();

        $routes = collect($app['router']->getRoutes()->getRoutes())
            ->map(fn (RoutingRoute $route): array => [
                'name' => (string) $route->getName(),
                'method' => collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->first(),
                'uri' => sampleUri($route),
            ])
            ->all();
    }

    return collect($routes)
        ->filter(fn (array $route): bool => str_starts_with($route['name'], $prefix))
        ->mapWithKeys(fn (array $route): array => [$route['method'].' '.$route['name'] => [$route['method'], $route['uri'], $route['name']]])
        ->all();
}

/**
 * The route's URI with every parameter replaced by a value that satisfies its constraint.
 */
function sampleUri(RoutingRoute $route): string
{
    $candidates = ['1', (string) Str::uuid(), (string) Str::ulid(), 'abc', 'en', 'stripe', 'content', 'pages'];

    return '/'.ltrim((string) preg_replace_callback('/\{(\w+)\??\}/', function (array $matches) use ($route, $candidates): string {
        $pattern = $route->wheres[$matches[1]] ?? null;

        if ($pattern === null) {
            return '1';
        }

        foreach ([...$candidates, ...explode('|', $pattern)] as $candidate) {
            if (preg_match('#^(?:'.$pattern.')$#u', $candidate) === 1) {
                return $candidate;
            }
        }

        return '1';
    }, $route->uri()), '/');
}

dataset('admin routes', fn (): array => protectedRoutes('admin.'));
dataset('portal routes', fn (): array => protectedRoutes('portal.'));
dataset('api routes', fn (): array => protectedRoutes('api.'));

test('guests are sent to the sign-in page from every admin route', function (string $method, string $uri, string $name) {
    expect(Route::getRoutes()->match(Request::create($uri, $method))->getName())->toBe($name);

    $this->call($method, $uri)->assertRedirect(route('login'));
})->with('admin routes');

test('a client account is refused on every admin route', function (string $method, string $uri) {
    $client = User::factory()->role(Role::Client)->create();

    // 403 from can:admin.access, or 404 when the route-model binding runs first and the record does not exist.
    expect($this->actingAs($client)->call($method, $uri)->status())->toBeIn([403, 404]);
})->with('admin routes');

test('a client account gets 403 on admin routes whose records exist', function () {
    $client = User::factory()->role(Role::Client)->create();
    $lead = Lead::factory()->create();
    $customer = Customer::factory()->create();

    foreach ([route('admin.dashboard'), route('admin.leads.show', $lead), route('admin.customers.show', $customer), route('admin.settings.index'), route('admin.audit-logs.index')] as $url) {
        $this->actingAs($client)->get($url)->assertForbidden();
    }
});

test('guests are sent to the sign-in page from every portal route', function (string $method, string $uri, string $name) {
    expect(Route::getRoutes()->match(Request::create($uri, $method))->getName())->toBe($name);

    $this->call($method, $uri)->assertRedirect(route('login'));
})->with('portal routes');

test('requests without a token are rejected on every api route', function (string $method, string $uri, string $name) {
    expect(Route::getRoutes()->match(Request::create($uri, $method))->getName())->toBe($name);

    $this->json($method, $uri)->assertUnauthorized();
})->with('api routes');

test('a signed-in staff session is not accepted by the api', function (string $method, string $uri) {
    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();

    $this->actingAs($admin)->json($method, $uri)->assertUnauthorized();
})->with('api routes');

test('every admin route requires sign-in, an active account, admin access and two-factor where required', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'admin.'));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $middleware = Route::gatherRouteMiddleware($route);

        expect($middleware)
            ->toContain(Authenticate::class, EnsureUserIsActive::class, 'Illuminate\Auth\Middleware\Authorize:admin.access');

        if (! in_array($route->getName(), PRE_TWO_FACTOR_ADMIN_ROUTES, true)) {
            expect($middleware)->toContain(RequireTwoFactor::class);
        }
    }
});

test('every portal route requires sign-in, an active account and portal access', function () {
    foreach (collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'portal.')) as $route) {
        expect(Route::gatherRouteMiddleware($route))->toContain(Authenticate::class, EnsureUserIsActive::class, EnsurePortalAccess::class);
    }
});

test('every api route requires a sanctum token and the mcp gate', function () {
    foreach (collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'api.')) as $route) {
        $middleware = Route::gatherRouteMiddleware($route);

        expect($middleware)->toContain('Illuminate\Auth\Middleware\Authenticate:sanctum')
            ->and(collect($middleware)->contains(fn (string $item): bool => Str::startsWith($item, AuthorizeMcpCall::class)))->toBeTrue();
    }
});

/*
| Guard against future regressions: an admin route must check a specific permission or policy, beyond the
| area-wide admin.access, through `can:` middleware, Gate/authorize calls in the action, or a Form Request whose
| authorize() is not a bare `return true`.
*/
test('every admin route has a permission or policy check beyond admin access', function () {
    $unchecked = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'admin.'))
        ->reject(fn (RoutingRoute $route): bool => in_array($route->getName(), SELF_SERVICE_ADMIN_ROUTES, true))
        ->reject(fn (RoutingRoute $route): bool => collect(Route::gatherRouteMiddleware($route))->contains(
            fn (mixed $middleware): bool => is_string($middleware)
                && str_starts_with($middleware, 'Illuminate\Auth\Middleware\Authorize:')
                && $middleware !== 'Illuminate\Auth\Middleware\Authorize:admin.access',
        ))
        ->reject(fn (RoutingRoute $route): bool => actionAuthorizes($route))
        ->map(fn (RoutingRoute $route): string => $route->getName().' ('.$route->getActionName().')')
        ->values()
        ->all();

    expect($unchecked)->toBe([]);
});

test('the self-service admin routes only expose the signed-in user\'s own data', function () {
    $staff = User::factory()->role(Role::SalesManager)->create();
    $other = User::factory()->role(Role::SalesManager)->create();
    $other->notify(new class extends Notification
    {
        /** @return list<string> */
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        /** @return array<string, string> */
        public function toArray(object $notifiable): array
        {
            return ['title' => 'Private to the other user', 'url' => '/admin/leads'];
        }
    });

    $notification = $other->notifications()->first();

    $this->actingAs($staff)->get(route('admin.notifications.index'))->assertSuccessful()->assertDontSee('Private to the other user');
    $this->actingAs($staff)->post(route('admin.notifications.read', $notification->id))->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();
});

test('the private disk is not served over http', function () {
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.local.upload'))->toBeFalse();
});

function actionAuthorizes(RoutingRoute $route): bool
{
    $action = $route->getActionName();
    [$class, $method] = str_contains($action, '@') ? explode('@', $action) : [$action, '__invoke'];

    if (! class_exists($class) || ! method_exists($class, $method)) {
        return false;
    }

    $reflection = new ReflectionMethod($class, $method);
    $source = implode('', array_slice(file((string) $reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

    if (preg_match('/Gate::(authorize|allows|denies|check|any)\(|->authorize\(|->can\(|->cannot\(/', $source) === 1) {
        return true;
    }

    foreach ($reflection->getParameters() as $parameter) {
        $type = $parameter->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

        if ($typeName !== null && is_subclass_of($typeName, FormRequest::class) && method_exists($typeName, 'authorize')) {
            $authorize = new ReflectionMethod($typeName, 'authorize');
            $body = implode('', array_slice(file((string) $authorize->getFileName()), $authorize->getStartLine() - 1, $authorize->getEndLine() - $authorize->getStartLine() + 1));

            if (preg_match('/return\s+true\s*;/', $body) !== 1) {
                return true;
            }
        }
    }

    return false;
}
