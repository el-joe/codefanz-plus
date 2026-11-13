<?php

use App\Enums\Role;
use App\Http\Requests\ContactMessageRequest;
use App\Models\AnalyticsSnapshot;
use App\Models\Lead;
use App\Models\User;
use App\Services\Analytics\AnalyticsConsent;
use App\Services\Analytics\AnalyticsSync;
use App\Services\Analytics\GoogleServiceAccount;
use App\Services\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Http::preventStrayRequests();
});

function configureGa4(bool $withSecret = true): void
{
    $settings = app(Settings::class);
    $settings->set('integrations', 'ga4_measurement_id', 'G-TEST1234');

    if ($withSecret) {
        $settings->set('integrations', 'ga4_api_secret', 'mp-secret-123', encrypted: true);
    }
}

/**
 * Saves a freshly generated service account key and returns its public key for signature checks.
 */
function configureGoogleServiceAccount(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);

    $settings = app(Settings::class);
    $settings->set('integrations', 'google_service_account_json', json_encode([
        'type' => 'service_account',
        'client_email' => 'reports@codefanz-test.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]), encrypted: true);
    $settings->set('integrations', 'ga4_property_id', '123456789');
    $settings->set('integrations', 'gsc_site_url', 'sc-domain:codefanz.test');

    return openssl_pkey_get_details($key)['key'];
}

/**
 * @return array<string, mixed>
 */
function analyticsContactPayload(): array
{
    return [
        'name' => 'Mona Hassan', 'email' => 'mona@example.com', 'topic' => 'quote',
        'message' => 'We need a booking platform for three clinics.', 'preferred_locale' => 'en', 'consent' => '1',
        ContactMessageRequest::TIMESTAMP => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
    ];
}

test('no banner and no GA4 tag without a measurement id', function () {
    $this->get(route('home', ['locale' => 'en']))->assertOk()
        ->assertDontSee('data-consent-banner', false)
        ->assertDontSee('googletagmanager.com');
});

test('the bilingual consent banner shows until a choice is made and GA4 loads only after opting in', function () {
    configureGa4();

    $this->get(route('home', ['locale' => 'en']))->assertOk()
        ->assertSee('data-consent-banner', false)
        ->assertSee(__('Necessary only'))->assertSee(__('Allow analytics'))
        ->assertDontSee('googletagmanager.com/gtag/js');

    $this->get(route('home', ['locale' => 'ar']))->assertOk()->assertSee('dir="rtl"', false)->assertSee('ملفات تعريف الارتباط');

    $this->withUnencryptedCookie(AnalyticsConsent::COOKIE, 'necessary')->get(route('home', ['locale' => 'en']))
        ->assertSee('data-consent-banner data-ga-id="G-TEST1234"', false)
        ->assertSee('hidden', false)
        ->assertDontSee('googletagmanager.com/gtag/js');

    $this->withUnencryptedCookie(AnalyticsConsent::COOKIE, 'analytics')->get(route('home', ['locale' => 'en']))
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST1234', false)
        ->assertSee("gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied'})", false)
        ->assertSee("gtag('config',\"G-TEST1234\",{anonymize_ip:true})", false);

    $this->withUnencryptedCookie(AnalyticsConsent::COOKIE, 'tampered')->get(route('home', ['locale' => 'en']))->assertDontSee('googletagmanager.com/gtag/js');
});

test('the no-JavaScript consent form stores the choice and returns to the same page only', function () {
    configureGa4();

    $this->from(route('about', ['locale' => 'ar']))->post(route('consent.store'), ['choice' => 'analytics'])
        ->assertRedirect('/ar/about')
        ->assertPlainCookie(AnalyticsConsent::COOKIE, 'analytics');

    $this->withHeader('referer', 'https://evil.example/phish')->post(route('consent.store'), ['choice' => 'necessary'])->assertRedirect('/');
    $this->post(route('consent.store'), ['choice' => 'everything'])->assertSessionHasErrors('choice');
});

test('a lead submission sends a Measurement Protocol event only with consent and saved credentials', function () {
    configureGa4();
    Http::fake(['www.google-analytics.com/*' => Http::response('', 204)]);

    // No consent: nothing is sent.
    $this->post('/en/contact', analyticsContactPayload())->assertRedirect();
    Http::assertNothingSent();

    // Consent given: one generate_lead event with the source, no personal data.
    $this->withUnencryptedCookie(AnalyticsConsent::COOKIE, 'analytics')->withUnencryptedCookie('_ga', 'GA1.1.111222333.1700000000')
        ->post('/en/contact', analyticsContactPayload())->assertRedirect();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://www.google-analytics.com/mp/collect')
            && $query === ['measurement_id' => 'G-TEST1234', 'api_secret' => 'mp-secret-123']
            && $request['client_id'] === '111222333.1700000000'
            && $request['events'][0]['name'] === 'generate_lead'
            && $request['events'][0]['params']['lead_source'] === 'contact_form'
            && ! str_contains($request->body(), 'mona@example.com');
    });
    expect(Lead::query()->count())->toBe(2);
});

test('without the API secret no Measurement Protocol event is sent even with consent', function () {
    configureGa4(withSecret: false);
    Http::fake();

    $this->withUnencryptedCookie(AnalyticsConsent::COOKIE, 'analytics')->post('/en/contact', analyticsContactPayload())->assertRedirect();

    Http::assertNothingSent();
});

test('the service account JWT is RS256-signed with the right claims and exchanged for a cached token', function () {
    $publicKey = configureGoogleServiceAccount();
    $google = app(GoogleServiceAccount::class);

    $jwt = $google->assertion([GoogleServiceAccount::SCOPE_ANALYTICS], 1_700_000_000);
    [$header, $claims, $signature] = explode('.', $jwt);
    $decode = fn (string $part) => base64_decode(strtr($part, '-_', '+/'));

    expect(json_decode($decode($header), true))->toBe(['alg' => 'RS256', 'typ' => 'JWT'])
        ->and(json_decode($decode($claims), true))->toBe([
            'iss' => 'reports@codefanz-test.iam.gserviceaccount.com',
            'scope' => GoogleServiceAccount::SCOPE_ANALYTICS,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => 1_700_000_000,
            'exp' => 1_700_003_600,
        ])
        ->and(openssl_verify($header.'.'.$claims, $decode($signature), $publicKey, OPENSSL_ALGO_SHA256))->toBe(1);

    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test-token', 'expires_in' => 3599, 'token_type' => 'Bearer'])]);

    expect($google->accessToken([GoogleServiceAccount::SCOPE_ANALYTICS]))->toBe('ya29.test-token')
        ->and($google->accessToken([GoogleServiceAccount::SCOPE_ANALYTICS]))->toBe('ya29.test-token');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        && count(explode('.', $request['assertion'])) === 3);
});

function fakeGoogleApis(int $ga4Status = 200, mixed $token = null): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => $token ?? Http::response(['access_token' => 'ya29.test-token', 'expires_in' => 3599]),
        'analyticsdata.googleapis.com/*' => function (Request $request) use ($ga4Status) {
            if ($ga4Status !== 200) {
                return Http::response(['error' => ['status' => 'PERMISSION_DENIED']], $ga4Status);
            }

            $dimension = $request['dimensions'][0]['name'];
            $rows = $dimension === 'date'
                ? collect(range(56, 1))->map(fn (int $daysAgo) => ['dimensionValues' => [['value' => today()->subDays($daysAgo)->format('Ymd')]], 'metricValues' => [['value' => '10'], ['value' => $daysAgo <= 28 ? '20' : '10'], ['value' => '50']]])->all()
                : [['dimensionValues' => [['value' => $dimension === 'eventName' ? 'generate_lead' : 'Organic Search']], 'metricValues' => [['value' => '300']]]];

            return Http::response(['rows' => $rows]);
        },
        'www.googleapis.com/webmasters/*' => function (Request $request) {
            $daily = $request['dimensions'] === ['date'];
            $rows = $daily
                ? collect(range(56, 1))->map(fn (int $daysAgo) => ['keys' => [today()->subDays($daysAgo)->toDateString()], 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.05, 'position' => 12.5])->all()
                : [['keys' => ['laravel agency'], 'clicks' => 42, 'impressions' => 900, 'ctr' => 0.046, 'position' => 8.1]];

            return Http::response(['rows' => $rows]);
        },
    ]);
}

test('the analytics dashboard says credentials are required and shows no numbers when not configured', function () {
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();
    Http::fake();

    $this->actingAs($seo)->get(route('admin.analytics.index'))->assertOk()
        ->assertSee('data-state="not_configured"', false)
        ->assertSee(__('Requires credentials'))
        ->assertSee(__('No GA4 data'))
        ->assertDontSee('data-analytics-chart', false);

    Http::assertNothingSent();
});

test('a sync stores daily snapshots and the dashboard shows real cached numbers with comparisons', function () {
    configureGoogleServiceAccount();
    fakeGoogleApis();
    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();

    $this->actingAs($seo)->post(route('admin.analytics.sync'))->assertRedirect();

    expect(AnalyticsSnapshot::query()->where('source', 'ga4')->pluck('report_key')->sort()->values()->all())->toBe(['channels', 'daily', 'events', 'landing_pages'])
        ->and(AnalyticsSnapshot::query()->where('source', 'gsc')->count())->toBe(5)
        ->and(app(AnalyticsSync::class)->status('ga4')['state'])->toBe('ok');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'properties/123456789:runReport') && $request->hasHeader('Authorization', 'Bearer ya29.test-token'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sites/sc-domain%3Acodefanz.test/searchAnalytics/query'));

    $this->actingAs($seo)->get(route('admin.analytics.index', ['days' => 28]))->assertOk()
        ->assertSee('data-state="connected"', false)
        ->assertSee('data-analytics-chart', false)
        ->assertSee('560')
        ->assertSee('+100% vs previous 28 days')
        ->assertSee('Organic Search')
        ->assertSee('laravel agency')
        ->assertSee('2,800');
});

test('an expired or revoked connection shows the reconnect state with the last cached data and never new numbers', function () {
    configureGoogleServiceAccount();
    // Two token exchanges succeed (GA4 + Search Console scopes), then the key is revoked.
    fakeGoogleApis(token: Http::sequence()
        ->push(['access_token' => 'ya29.test-token', 'expires_in' => 3599])
        ->push(['access_token' => 'ya29.test-token', 'expires_in' => 3599])
        ->whenEmpty(Http::response(['error' => 'invalid_grant'], 400)));
    app(AnalyticsSync::class)->run();
    $fetchedAt = AnalyticsSnapshot::query()->where('source', 'ga4')->value('fetched_at');

    $this->travel(1)->days();
    app(GoogleServiceAccount::class)->forgetTokens();

    expect(app(AnalyticsSync::class)->run()['ga4'])->toContain('invalid_grant');

    $seo = User::factory()->role(Role::SeoSpecialist)->withConfirmedTwoFactor()->create();
    $this->actingAs($seo)->get(route('admin.analytics.index'))->assertOk()
        ->assertSee('data-state="reconnect"', false)
        ->assertSee(__('Reconnect Google to resume the daily sync'))
        ->assertSee('nothing is invented while disconnected')
        ->assertSee($fetchedAt->translatedFormat('j M Y, H:i'))
        ->assertSee('560');

    expect(AnalyticsSnapshot::query()->where('source', 'ga4')->where('report_key', 'daily')->count())->toBe(1);
});

test('a permission error from the Data API asks to reconnect', function () {
    configureGoogleServiceAccount();
    fakeGoogleApis(ga4Status: 403);

    app(AnalyticsSync::class)->run();

    expect(app(AnalyticsSync::class)->status('ga4')['state'])->toBe('reconnect')
        ->and(app(AnalyticsSync::class)->status('gsc')['state'])->toBe('ok')
        ->and(AnalyticsSnapshot::query()->where('source', 'ga4')->count())->toBe(0);
});
