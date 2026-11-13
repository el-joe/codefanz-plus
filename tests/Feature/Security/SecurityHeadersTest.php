<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Analytics\AnalyticsConsent;
use App\Services\Settings;

/*
| M11: SecurityHeaders middleware. Every response carries the hardening headers; HTML pages get a nonce-based CSP
| whose nonce is the one Vite prints on @vite / @fonts tags and the layouts print on their inline scripts.
*/

function cspDirectives(string $policy): array
{
    return collect(explode(';', $policy))
        ->map(fn (string $directive): array => preg_split('/\s+/', trim($directive)))
        ->filter(fn (array $parts): bool => $parts[0] !== '')
        ->mapWithKeys(fn (array $parts): array => [array_shift($parts) => $parts])
        ->all();
}

test('public pages in both languages send the hardening headers and a nonce-based policy', function (string $locale) {
    $response = $this->get(route('home', ['locale' => $locale]))->assertSuccessful();

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()', 'geolocation=()', 'microphone=()');

    $csp = cspDirectives((string) $response->headers->get('Content-Security-Policy'));
    preg_match("/'nonce-([A-Za-z0-9]+)'/", implode(' ', $csp['script-src']), $matches);
    $nonce = $matches[1] ?? null;

    expect($nonce)->not->toBeNull()
        ->and($csp['default-src'])->toBe(["'self'"])
        ->and($csp['script-src'])->not->toContain("'unsafe-inline'", "'unsafe-eval'")
        ->and($csp['style-src'])->toContain("'nonce-{$nonce}'")
        ->and($csp['object-src'])->toBe(["'none'"])
        ->and($csp['frame-ancestors'])->toBe(["'none'"])
        ->and($csp['base-uri'])->toBe(["'self'"])
        ->and($csp['frame-src'])->toBe(['https://www.youtube-nocookie.com', 'https://player.vimeo.com']);

    $html = $response->getContent();

    // Every executable script carries the nonce; JSON-LD blocks are data and do not need it.
    preg_match_all('/<script(?![^>]*type="application\/(?:ld\+)?json")[^>]*>/', $html, $scripts);
    expect($scripts[0])->not->toBeEmpty();
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$nonce.'"');
    }

    preg_match_all('/<style[^>]*>/', $html, $styles);
    foreach ($styles[0] as $tag) {
        expect($tag)->toContain('nonce="'.$nonce.'"');
    }

    expect($html)->toContain('<script type="application/ld+json">');
})->with(['en', 'ar']);

test('every request gets a fresh nonce', function () {
    $first = $this->get(route('home', ['locale' => 'en']))->headers->get('Content-Security-Policy');
    $second = $this->get(route('home', ['locale' => 'en']))->headers->get('Content-Security-Policy');

    expect($first)->not->toBe($second);
});

test('google analytics hosts are allowed only when a measurement id is configured', function () {
    $without = cspDirectives((string) $this->get(route('home', ['locale' => 'en']))->headers->get('Content-Security-Policy'));
    expect($without['script-src'])->not->toContain('https://www.googletagmanager.com');

    app(Settings::class)->set('integrations', 'ga4_measurement_id', 'G-TEST1234');

    $response = $this->withUnencryptedCookie(AnalyticsConsent::COOKIE, AnalyticsConsent::ANALYTICS)->get(route('home', ['locale' => 'en']));
    $with = cspDirectives((string) $response->headers->get('Content-Security-Policy'));

    expect($with['script-src'])->toContain('https://www.googletagmanager.com')
        ->and($with['connect-src'])->toContain('https://*.google-analytics.com');
    preg_match("/'nonce-([A-Za-z0-9]+)'/", implode(' ', $with['script-src']), $matches);
    $response->assertSee('<script async nonce="'.$matches[1].'" src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234">', false);
});

test('https requests get HSTS and upgrade insecure requests', function () {
    $response = $this->get('https://localhost/en');

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    expect($response->headers->get('Content-Security-Policy'))->toEndWith('upgrade-insecure-requests');
});

test('admin pages with the rich text editor and charts expose the nonce to scripts', function () {
    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();

    $response = $this->actingAs($admin)->get(route('admin.posts.create'))->assertSuccessful();
    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

    // TipTap injects its base styles with this nonce (injectNonce); style attributes stay allowed for the editor.
    $response->assertSee('<meta name="csp-nonce" content="'.$matches[1].'">', false);
    expect(cspDirectives((string) $response->headers->get('Content-Security-Policy'))['style-src-attr'])->toBe(["'unsafe-inline'"]);
});

test('non-html responses get the hardening headers but no content security policy', function () {
    $response = $this->get(route('robots'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeaderMissing('Content-Security-Policy');
});

test('the policy can be switched to report-only', function () {
    config(['codefanz.security.csp.report_only' => true]);

    $this->get(route('home', ['locale' => 'en']))
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only');
});
