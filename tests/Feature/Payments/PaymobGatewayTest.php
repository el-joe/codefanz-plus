<?php

use App\Enums\CustomerUserRole;
use App\Enums\MilestoneStatus;
use App\Enums\PaymentEventSource;
use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Models\Customer;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymobGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Transaction Processed example from Paymob's "HMAC Transaction Callback" page, field by field.
 *
 * @return array<string, mixed>
 */
function paymobTransaction(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 192036465,
        'pending' => false,
        'amount_cents' => 100000,
        'success' => true,
        'is_auth' => false,
        'is_capture' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'is_refunded' => false,
        'is_3d_secure' => true,
        'integration_id' => 4097558,
        'has_parent_transaction' => false,
        'order' => ['id' => 217503754, 'merchant_order_id' => null],
        'created_at' => '2024-06-13T11:33:44.592345',
        'currency' => 'EGP',
        'source_data' => ['pan' => '2346', 'type' => 'card', 'sub_type' => 'MasterCard'],
        'error_occured' => false,
        'owner' => 302852,
        'data' => ['message' => 'Approved'],
    ], $overrides);
}

function postPaymobCallback(TestCase $test, array $transaction, ?string $hmac = null): TestResponse
{
    $hmac ??= PaymobGateway::transactionHmac($transaction, 'PAYMOBHMACSECRET');

    return $test->postJson(route('webhooks.payments', 'paymob').'?hmac='.$hmac, ['type' => 'TRANSACTION', 'obj' => $transaction]);
}

/**
 * @param  array<string, mixed>|int  $inquiry
 */
function fakePaymob(array|int $inquiry = []): void
{
    Http::fake(function (Request $request) use ($inquiry) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match ($path) {
            '/v1/intention/' => Http::response(['id' => 'pi_test_abc', 'client_secret' => 'egy_csk_test_secret', 'intention_order_id' => 217503754], 201),
            '/api/auth/tokens' => Http::response(['token' => 'paymob-auth-token']),
            '/api/ecommerce/orders/transaction_inquiry' => is_int($inquiry)
                ? Http::response(['detail' => 'not found'], $inquiry)
                : Http::response(paymobTransaction($inquiry)),
            default => Http::response(['detail' => 'unexpected'], 404),
        };
    });
}

beforeEach(function () {
    Notification::fake();
    Event::fake([PaymentSucceeded::class]);
    configurePaymob();

    $this->customer = Customer::factory()->create(['company_name' => 'Nile Clinics', 'phone' => '+201000000000']);
    User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create();
    $this->project = Project::factory()->forCustomer($this->customer)->create(['currency' => 'EGP']);
    $this->milestone = Milestone::factory()->for($this->project)->status(MilestoneStatus::InProgress)->create(['amount_minor' => 300_000, 'currency' => 'EGP']);
    $this->link = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 100_000, 'gateways' => ['paymob']]);
});

test('the HMAC follows the documented field order with a fixed vector', function () {
    // Concatenated string exactly as printed in Paymob's HMAC Transaction Callback example.
    $documented = '1000002024-06-13T11:33:44.592345EGPfalsefalse1920364654097558truefalsefalsefalsetruefalse217503754302852false2346MasterCardcardtrue';

    expect(PaymobGateway::transactionHmac(paymobTransaction(), 'PAYMOBHMACSECRET'))
        ->toBe(hash_hmac('sha512', $documented, 'PAYMOBHMACSECRET'));
});

test('checkout creates a Paymob intention in piasters and redirects to unified checkout', function () {
    fakePaymob();

    $response = $this->post(route('pay.checkout', $this->link), ['gateway' => 'paymob']);

    $payment = Payment::query()->sole();
    $response->assertRedirect('https://accept.paymob.com/unifiedcheckout/?publicKey=egy_pk_test_codefanz&clientSecret=egy_csk_test_secret');

    expect($payment->gateway_payment_id)->toBe('217503754')
        ->and($payment->metadata['paymob_intention_id'])->toBe('pi_test_abc');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://accept.paymob.com/v1/intention/'
        && $request->hasHeader('Authorization', 'Token egy_sk_test_codefanz')
        && $request['amount'] === 100_000
        && $request['currency'] === 'EGP'
        && $request['payment_methods'] === [4097558, 4097559]
        && $request['items'][0]['amount'] === 100_000
        && $request['special_reference'] === $payment->ulid
        && $request['billing_data']['phone_number'] === '+201000000000'
        && $request['notification_url'] === route('webhooks.payments', 'paymob')
        && $request['redirection_url'] === route('pay.return', [$this->link, $payment]));
});

test('a USD link cannot use Paymob', function () {
    fakePaymob();
    $usdLink = PaymentLink::factory()->create(['project_id' => $this->project->id, 'currency' => 'USD', 'gateways' => ['paymob']]);

    $this->get(route('pay.show', $usdLink))->assertSee('Online payment is not available right now');
    $this->post(route('pay.checkout', $usdLink), ['gateway' => 'paymob'])->assertSessionHasErrors('gateway');

    expect(Payment::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('a valid transaction callback is verified through the inquiry API before the payment is paid', function () {
    fakePaymob();
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paymob', 'gateway_payment_id' => '217503754'])->refresh();

    postPaymobCallback($this, paymobTransaction(['order' => ['merchant_order_id' => $payment->ulid]]))
        ->assertOk()->assertJson(['status' => 'processed']);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->verified_via)->toBe(PaymentEventSource::Webhook)
        ->and($payment->metadata['transaction_id'])->toBe('192036465')
        ->and($payment->methodLabel())->toBe('Card')
        ->and($this->milestone->refresh()->paid_minor)->toBe(100_000);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/ecommerce/orders/transaction_inquiry')
        && $request->hasHeader('Authorization', 'Bearer paymob-auth-token')
        && $request['order_id'] === 217503754);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/auth/tokens') && $request['api_key'] === 'paymobApiKey123');
});

test('a tampered callback is rejected and stored as unverified', function () {
    fakePaymob();
    Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paymob', 'gateway_payment_id' => '217503754']);

    $transaction = paymobTransaction();
    $hmac = PaymobGateway::transactionHmac($transaction, 'PAYMOBHMACSECRET');
    $transaction['amount_cents'] = 1;

    postPaymobCallback($this, $transaction, $hmac)->assertStatus(400);

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->signature_valid)->toBeFalse()
        ->and(WebhookEvent::query()->sole()->event_id)->toStartWith('unverified-');
    Http::assertNothingSent();
});

test('a callback that claims success is not trusted when the API says the transaction failed', function () {
    fakePaymob(['success' => false, 'data' => ['message' => 'Do not honour']]);
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paymob', 'gateway_payment_id' => '217503754'])->refresh();

    postPaymobCallback($this, paymobTransaction())->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('Do not honour');
});

test('an inquiry amount that differs from the payment is never marked paid', function () {
    fakePaymob(['amount_cents' => 99_999]);
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paymob', 'gateway_payment_id' => '217503754'])->refresh();

    postPaymobCallback($this, paymobTransaction())->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

test('an order without a transaction yet stays pending on the return page', function () {
    fakePaymob(404);
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'paymob', 'gateway_payment_id' => '217503754'])->refresh();

    $this->get(route('pay.return', [$this->link, $payment]))->assertSuccessful()->assertSee('confirming your payment');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});
