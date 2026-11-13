<?php

use App\Enums\AutomationRunStatus;
use App\Enums\Role;
use App\Enums\SeoIssueType;
use App\Events\RadarProjectScored;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\RadarProject;
use App\Models\SeoIssue;
use App\Models\Service;
use App\Models\User;
use App\Services\Security\OutboundUrlGuard;
use Database\Seeders\RadarPlatformSeeder;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/*
| M11 SSRF: URLs from content (SEO external link check) and admin input (n8n webhooks) may only reach public hosts.
*/

beforeEach(function () {
    OutboundUrlGuard::resolveHostsUsing(fn (string $host): array => match ($host) {
        'internal.example.test' => ['10.0.0.5'],
        'metadata.example.test' => ['169.254.169.254'],
        'mixed.example.test' => ['93.184.216.34', '192.168.1.10'],
        'v6-loopback.example.test' => ['::1'],
        'nowhere.example.test' => [],
        default => ['93.184.216.34'],
    });
});

test('only http(s) urls on public addresses are allowed', function (string $url, ?string $reason) {
    expect(app(OutboundUrlGuard::class)->reason($url))->toBe($reason);
})->with([
    'public host' => ['https://n8n.example.test/webhook/x', null],
    'public ip' => ['http://93.184.216.34/', null],
    'private ipv4 literal' => ['http://10.1.2.3/', 'private'],
    'loopback' => ['http://127.0.0.1:8080/', 'private'],
    'localhost name' => ['http://localhost:5678/webhook', 'private'],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data/', 'private'],
    'carrier-grade nat' => ['http://100.64.0.1/', 'private'],
    'ipv6 loopback literal' => ['http://[::1]/', 'private'],
    'ipv4-mapped ipv6' => ['http://[::ffff:127.0.0.1]/', 'private'],
    'unspecified' => ['http://0.0.0.0/', 'private'],
    'host resolving privately' => ['https://internal.example.test/', 'private'],
    'host resolving to metadata' => ['https://metadata.example.test/', 'private'],
    'any private record' => ['https://mixed.example.test/', 'private'],
    'ipv6 loopback record' => ['https://v6-loopback.example.test/', 'private'],
    'unresolvable' => ['https://nowhere.example.test/', 'unresolvable'],
    'file scheme' => ['file:///etc/passwd', 'scheme'],
    'gopher scheme' => ['gopher://example.test/', 'scheme'],
    'no host' => ['https:///path', 'scheme'],
]);

test('private addresses are allowed only when explicitly enabled for local development', function () {
    config(['codefanz.security.allow_private_outbound' => true]);

    expect(app(OutboundUrlGuard::class)->isAllowed('http://localhost:5678/webhook'))->toBeTrue()
        ->and(app(OutboundUrlGuard::class)->isAllowed('file:///etc/passwd'))->toBeFalse();
});

test('request options pin the vetted address and re-check redirects', function () {
    $guard = app(OutboundUrlGuard::class);
    $options = $guard->requestOptions('https://n8n.example.test/hook', 3);

    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['n8n.example.test:443:93.184.216.34'])
        ->and($options['allow_redirects']['max'])->toBe(3)
        ->and($guard->requestOptions('https://n8n.example.test/hook')['allow_redirects'])->toBeFalse();

    $redirect = $options['allow_redirects']['on_redirect'];
    $request = new GuzzleHttp\Psr7\Request('HEAD', 'https://n8n.example.test/hook');
    $response = new Response(302);

    $redirect($request, $response, new Uri('https://other.example.test/'));
    expect(fn () => $redirect($request, $response, new Uri('http://169.254.169.254/')))->toThrow(RuntimeException::class);
});

test('an outbound n8n workflow cannot target a private address', function (string $url) {
    $admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();

    $this->actingAs($admin)->post(route('admin.automation.store'), [
        'name' => 'Internal probe', 'direction' => 'outbound', 'url' => $url, 'events' => ['lead.created'], 'is_enabled' => '1',
    ])->assertSessionHasErrors(['url' => __('The webhook URL must point to a public host.')]);

    expect(AutomationWorkflow::query()->count())->toBe(0);
})->with(['http://169.254.169.254/latest/meta-data/', 'https://internal.example.test/hook', 'http://localhost:5678/webhook']);

test('a stored workflow whose host now resolves privately is not called', function () {
    Http::preventStrayRequests();
    Http::fake();
    $this->seed(RadarPlatformSeeder::class);
    AutomationWorkflow::factory()->create(['events' => ['radar.project_scored'], 'url' => 'https://internal.example.test/webhook']);

    event(new RadarProjectScored(RadarProject::factory()->create()));

    $run = AutomationRun::query()->sole();
    expect($run->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->error)->toContain('public address');
    Http::assertNothingSent();
});

test('the seo link check never requests links that point at private addresses', function () {
    config(['codefanz.seo.audit.check_external_links' => true]);
    Service::factory()->withTranslation('en', ['title' => 'Links', 'slug' => 'links'])->create();
    $page = route('services.show', ['locale' => 'en', 'slug' => 'links']);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($page) {
        $html = '<!DOCTYPE html><html lang="en"><head><title>A page title that is long enough</title><meta name="description" content="'.str_repeat('Description ', 10).'"><link rel="canonical" href="'.$request->url().'"></head><body><h1>Heading</h1>';

        return Http::response($request->url() === $page
            ? $html.'<a href="http://169.254.169.254/latest/meta-data/">x</a><a href="https://internal.example.test/admin">y</a><a href="https://public.example.test/ok">z</a></body></html>'
            : $html.'</body></html>');
    });

    Artisan::call('seo:audit', ['--sync' => true]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '169.254.169.254') || str_contains($request->url(), 'internal.example.test'));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD' && $request->url() === 'https://public.example.test/ok');
    expect(SeoIssue::query()->where('type', SeoIssueType::BrokenExternalLink)->pluck('details')->pluck('link')->sort()->values()->all())
        ->toBe(['http://169.254.169.254/latest/meta-data/', 'https://internal.example.test/admin']);
});
