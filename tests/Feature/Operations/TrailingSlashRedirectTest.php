<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * The HTTP test helpers strip trailing slashes from URIs, so the raw request goes straight through the kernel.
 */
function sendRawRequest(string $method, string $uri): TestResponse
{
    return TestResponse::fromBaseResponse(app(Kernel::class)->handle(Request::create($uri, $method)));
}

test('a trailing slash is redirected permanently to the canonical URL', function (string $from, string $to) {
    sendRawRequest('GET', $from)->assertStatus(301)->assertRedirect($to);
})->with([
    'locale home' => ['http://localhost/en/', 'http://localhost/en'],
    'arabic page' => ['http://localhost/ar/about/', 'http://localhost/ar/about'],
    'query kept' => ['http://localhost/en/blog/?page=2', 'http://localhost/en/blog?page=2'],
    'repeated slashes' => ['http://localhost/en//', 'http://localhost/en'],
]);

test('HEAD requests are redirected too', function () {
    sendRawRequest('HEAD', 'http://localhost/en/')->assertStatus(301);
});

test('a path that starts with two slashes stays on this host', function () {
    $response = sendRawRequest('GET', 'http://localhost//evil.example/');

    $response->assertStatus(301);
    expect($response->headers->get('Location'))->toBe('http://localhost/evil.example');
});

test('the root and slash-less URLs are not redirected', function () {
    sendRawRequest('GET', 'http://localhost/')->assertStatus(302); // language negotiation, not this rule
    sendRawRequest('GET', 'http://localhost/en')->assertOk();
});

test('non-GET requests are not redirected', function () {
    expect(sendRawRequest('POST', 'http://localhost/webhooks/payments/stripe/')->getStatusCode())->not->toBe(301);
});
