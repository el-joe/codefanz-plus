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
use App\Services\Payments\FawryGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * @param  array<string, mixed>  $status
 */
function fakeFawry(array $status = []): void
{
    Http::fake([
        'atfawry.fawrystaging.com/ECommerceWeb/Fawry/payments/charge' => Http::response([
            'type' => 'ChargeResponse', 'referenceNumber' => '963455678', 'merchantRefNumber' => 'x',
            'expirationTime' => 1_900_000_000_000, 'statusCode' => 200, 'statusDescription' => 'Operation done successfully',
        ]),
        'atfawry.fawrystaging.com/fawrypay-api/api/payments/init' => Http::response('"https://atfawry.fawrystaging.com/atfawry/plugin/checkout?token=abc"'),
        'atfawry.fawrystaging.com/ECommerceWeb/Fawry/payments/status/v2*' => Http::response([
            'type' => 'PaymentStatusResponse', 'referenceNumber' => '963455678', 'paymentAmount' => 1520.5, 'orderAmount' => 1500,
            'fawryFees' => 20.5, 'orderStatus' => 'UNPAID', 'paymentMethod' => 'PAYATFAWRY', 'statusCode' => 200, 'statusDescription' => 'Operation done successfully',
            ...$status,
        ]),
        '*' => Http::response(['statusCode' => 9999], 404),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fawryNotification(string $merchantReference, array $overrides = []): array
{
    $event = [
        'requestId' => 'c72827d084ea4b88949d91dd2db4996e',
        'fawryRefNumber' => '963455678',
        'merchantRefNumber' => $merchantReference,
        'customerMobile' => '01234567891',
        'paymentAmount' => 1520.5,
        'orderAmount' => 1500,
        'fawryFees' => 20.5,
        'orderStatus' => 'PAID',
        'paymentMethod' => 'PAYATFAWRY',
        'paymentTime' => 1_763_000_000_000,
        'paymentRefrenceNumber' => '24567',
        ...$overrides,
    ];

    return [...$event, 'messageSignature' => FawryGateway::notificationSignature($event, 'fawry-secure-key')];
}

function postFawryNotification(TestCase $test, array $event): TestResponse
{
    return $test->postJson(route('webhooks.payments', 'fawry'), $event);
}

beforeEach(function () {
    Notification::fake();
    Event::fake([PaymentSucceeded::class]);
    configureFawry();

    $this->customer = Customer::factory()->create(['phone' => '01234567891', 'email' => 'payer@example.test']);
    User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create();
    $this->project = Project::factory()->forCustomer($this->customer)->create();
    $this->link = PaymentLink::factory()->create(['project_id' => $this->project->id, 'amount_minor' => 150_000, 'currency' => 'EGP', 'gateways' => ['fawry']]);
});

test('the notification V2 signature uses the documented fields with two-decimal amounts', function () {
    $event = fawryNotification('01JABCDEF');

    // fawryRefNumber + merchantRefNumber + paymentAmount + orderAmount + orderStatus + paymentMethod + paymentRefrenceNumber + secureKey
    expect($event['messageSignature'])->toBe(hash('sha256', '963455678'.'01JABCDEF'.'1520.50'.'1500.00'.'PAID'.'PAYATFAWRY'.'24567'.'fawry-secure-key'));
});

test('a reference-number checkout shows the Fawry reference and expiry while the payment stays pending', function () {
    fakeFawry();

    $response = $this->post(route('pay.checkout', $this->link), ['gateway' => 'fawry']);
    $payment = Payment::query()->sole();

    $response->assertRedirect(route('pay.return', [$this->link, $payment]));

    expect($payment->gateway_payment_id)->toBe($payment->ulid)
        ->and($payment->metadata['fawry_reference_number'])->toBe('963455678')
        ->and($payment->status)->toBe(PaymentStatus::Pending);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/ECommerceWeb/Fawry/payments/charge')
        && $request['merchantCode'] === 'FAWRYMERCHANT1'
        && $request['merchantRefNum'] === $payment->ulid
        && $request['paymentMethod'] === 'PAYATFAWRY'
        && (string) $request['amount'] === '1500'
        && $request['currencyCode'] === 'EGP'
        && $request['signature'] === hash('sha256', 'FAWRYMERCHANT1'.$payment->ulid.$payment->customer_id.'PAYATFAWRY'.'1500.00'.'fawry-secure-key'));

    $this->get(route('pay.return', [$this->link, $payment]))
        ->assertSuccessful()
        ->assertSee('963455678')
        ->assertSee('Pay before')
        ->assertSee('data-offline-reference', false);

    $this->withSession(['locale' => 'ar'])->get(route('pay.return', [$this->link, $payment]))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('ادفع باستخدام الرقم المرجعي لفوري')
        ->assertSee('963455678');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

test('the card method signs the documented init fields and redirects to FawryPay', function () {
    configureFawry('CARD');
    fakeFawry();

    $this->post(route('pay.checkout', $this->link), ['gateway' => 'fawry'])->assertRedirect('https://atfawry.fawrystaging.com/atfawry/plugin/checkout?token=abc');
    $payment = Payment::query()->sole();
    $returnUrl = route('pay.return', [$this->link, $payment]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/fawrypay-api/api/payments/init')
        && $request['returnUrl'] === $returnUrl
        && $request['signature'] === hash('sha256', 'FAWRYMERCHANT1'.$payment->ulid.$payment->customer_id.$returnUrl.$this->link->reference.'1'.'1500.00'.'fawry-secure-key'));
});

test('a pay-at-outlet payment becomes paid only after a verified notification and status check', function () {
    fakeFawry(['orderStatus' => 'PAID', 'paymentTime' => 1_763_000_000_000]);
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'fawry', 'metadata' => ['fawry_payment_method' => 'PAYATFAWRY']])->refresh();
    $payment->forceFill(['gateway_payment_id' => $payment->ulid])->save();

    postFawryNotification($this, fawryNotification($payment->ulid))->assertOk()->assertJson(['status' => 'processed']);

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->amount_minor)->toBe(150_000)
        ->and($payment->metadata['fawry_fees_minor'])->toBe(2050)
        ->and($payment->paid_at->getTimestamp())->toBe(1_763_000_000);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/ECommerceWeb/Fawry/payments/status/v2')
        && $request['merchantRefNumber'] === $payment->ulid
        && $request['signature'] === hash('sha256', 'FAWRYMERCHANT1'.$payment->ulid.'fawry-secure-key'));
});

test('a notification for an unpaid reference keeps the payment pending', function () {
    fakeFawry(['orderStatus' => 'UNPAID']);
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'fawry'])->refresh();
    $payment->forceFill(['gateway_payment_id' => $payment->ulid])->save();

    // The body claims PAID but the status API says UNPAID.
    postFawryNotification($this, fawryNotification($payment->ulid))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

test('a notification with a bad signature is rejected', function () {
    fakeFawry(['orderStatus' => 'PAID']);
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'fawry'])->refresh();
    $payment->forceFill(['gateway_payment_id' => $payment->ulid])->save();

    $event = fawryNotification($payment->ulid);
    $event['orderAmount'] = 1;

    postFawryNotification($this, $event)->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->signature_valid)->toBeFalse();
    Http::assertNothingSent();
});

test('an expired reference cancels the payment', function () {
    fakeFawry(['orderStatus' => 'EXPIRED']);
    $payment = Payment::factory()->for($this->link, 'link')->create(['gateway' => 'fawry'])->refresh();
    $payment->forceFill(['gateway_payment_id' => $payment->ulid])->save();

    postFawryNotification($this, fawryNotification($payment->ulid, ['orderStatus' => 'EXPIRED']))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);
});

test('Fawry only takes Egyptian pounds', function () {
    $usdLink = PaymentLink::factory()->create(['project_id' => $this->project->id, 'currency' => 'USD', 'gateways' => ['fawry']]);

    $this->post(route('pay.checkout', $usdLink), ['gateway' => 'fawry'])->assertSessionHasErrors('gateway');
});
