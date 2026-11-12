<?php

use App\Enums\CustomerUserRole;
use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function payTabsTransaction(array $overrides = []): array
{
    return array_replace_recursive([
        'tran_ref' => 'TST2231500000001',
        'tran_type' => 'Sale',
        'cart_id' => 'cart',
        'cart_currency' => 'EGP',
        'cart_amount' => '1500.00',
        'tran_currency' => 'EGP',
        'tran_total' => '1500.00',
        'payment_result' => ['response_status' => 'A', 'response_code' => '831000', 'response_message' => 'Authorised', 'transaction_time' => '2026-11-12T10:00:00Z'],
        'payment_info' => ['payment_method' => 'Visa', 'card_scheme' => 'Visa'],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $query
 */
function fakePayTabs(array $query = [], string $host = 'https://secure-egypt.paytabs.com'): void
{
    Http::fake([
        $host.'/payment/request' => Http::response(['tran_ref' => 'TST2231500000001', 'redirect_url' => 'https://secure-egypt.paytabs.com/payment/page/ABC']),
        $host.'/payment/query' => Http::response(payTabsTransaction($query)),
        '*' => Http::response(['code' => 404], 404),
    ]);
}

function postPayTabsCallback(TestCase $test, array $body, ?string $signature = null): TestResponse
{
    $raw = json_encode($body, JSON_THROW_ON_ERROR);
    $signature ??= hash_hmac('sha256', $raw, 'SJNPAYTABSKEY-123');

    return $test->call('POST', route('webhooks.payments', 'paytabs'), server: ['HTTP_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $raw);
}

beforeEach(function () {
    Notification::fake();
    Event::fake([PaymentSucceeded::class]);
    configurePayTabs();

    $this->customer = Customer::factory()->create();
    User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create();
    $this->project = Project::factory()->forCustomer($this->customer)->create();
    $this->link = PaymentLink::factory()->create(['project_id' => $this->project->id, 'amount_minor' => 150_000, 'currency' => 'EGP', 'gateways' => ['paytabs']]);
});

test('checkout sends a hosted payment page request with decimal amounts to the region host', function () {
    fakePayTabs();

    $this->post(route('pay.checkout', $this->link), ['gateway' => 'paytabs'])->assertRedirect('https://secure-egypt.paytabs.com/payment/page/ABC');

    $payment = Payment::query()->sole();
    expect($payment->gateway_payment_id)->toBe('TST2231500000001');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://secure-egypt.paytabs.com/payment/request'
        && $request->hasHeader('authorization', 'SJNPAYTABSKEY-123')
        && $request['profile_id'] === 98765
        && $request['tran_type'] === 'sale'
        && $request['tran_class'] === 'ecom'
        && $request['cart_id'] === $payment->ulid
        && $request['cart_currency'] === 'EGP'
        && (string) $request['cart_amount'] === '1500'
        && $request['callback'] === route('webhooks.payments', 'paytabs')
        && $request['return'] === route('pay.return', [$this->link, $payment]));
});

test('the region decides the API host and the currencies', function () {
    configurePayTabs('SAU');
    $gateway = app(PaymentGatewayManager::class)->driver('paytabs');

    expect($gateway->supportsCurrency('SAR'))->toBeTrue()
        ->and($gateway->supportsCurrency('EGP'))->toBeFalse();

    fakePayTabs(host: 'https://secure.paytabs.sa');
    $sarLink = PaymentLink::factory()->create(['project_id' => $this->project->id, 'currency' => 'SAR', 'gateways' => ['paytabs']]);

    $this->post(route('pay.checkout', $sarLink), ['gateway' => 'paytabs'])->assertRedirect();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://secure.paytabs.sa/payment/request');

    // The Egypt link's currency is not supported by a Saudi profile.
    $this->post(route('pay.checkout', $this->link), ['gateway' => 'paytabs'])->assertSessionHasErrors('gateway');
});

test('a signed callback is verified with the query API and marks the payment paid', function () {
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paytabs', 'gateway_payment_id' => 'TST2231500000001'])->refresh();
    fakePayTabs(['cart_id' => $payment->ulid]);

    postPayTabsCallback($this, payTabsTransaction(['cart_id' => $payment->ulid]))->assertOk()->assertJson(['status' => 'processed']);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->amount_minor)->toBe(150_000)
        ->and($payment->metadata['response_status'])->toBe('A');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://secure-egypt.paytabs.com/payment/query'
        && $request['tran_ref'] === 'TST2231500000001' && $request['profile_id'] === 98765);

    // The same delivery again is a no-op.
    postPayTabsCallback($this, payTabsTransaction(['cart_id' => $payment->ulid]))->assertOk()->assertJson(['status' => 'duplicate']);
});

test('a callback with a wrong signature is rejected', function () {
    fakePayTabs();
    Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paytabs', 'gateway_payment_id' => 'TST2231500000001']);

    postPayTabsCallback($this, payTabsTransaction(), hash_hmac('sha256', 'other body', 'SJNPAYTABSKEY-123'))->assertStatus(400);

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->signature_valid)->toBeFalse();
});

test('declined and on-hold query results map to failed and pending', function (string $responseStatus, PaymentStatus $expected) {
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paytabs', 'gateway_payment_id' => 'TST2231500000001'])->refresh();
    fakePayTabs(['cart_id' => $payment->ulid, 'payment_result' => ['response_status' => $responseStatus, 'response_message' => 'Declined']]);

    $this->get(route('pay.return', [$this->link, $payment]))->assertSuccessful();

    expect($payment->refresh()->status)->toBe($expected);
})->with([
    'declined' => ['D', PaymentStatus::Failed],
    'on hold' => ['H', PaymentStatus::Pending],
    'expired' => ['X', PaymentStatus::Cancelled],
]);
