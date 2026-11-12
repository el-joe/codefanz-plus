<?php

use App\Actions\Payments\RecomputeMilestonePaid;
use App\Enums\CustomerUserRole;
use App\Enums\MilestoneStatus;
use App\Enums\PaymentEventSource;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundRequested;
use App\Notifications\RefundStatusChanged;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    configureStripe();

    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);
    $this->client = User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create();
    $this->manager = User::factory()->role(Role::ProjectManager)->create();
    $this->finance = User::factory()->role(Role::FinanceManager)->withConfirmedTwoFactor()->create();
    $this->financeTwo = User::factory()->role(Role::FinanceManager)->withConfirmedTwoFactor()->create();
    $this->project = Project::factory()->forCustomer($this->customer)->create();
    $this->milestone = Milestone::factory()->for($this->project)->status(MilestoneStatus::InProgress)->create(['amount_minor' => 450_000]);
    $this->link = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 150_000]);
    $this->payment = paidPayment($this->link);
});

/**
 * A verified Stripe payment of the link's amount, with the milestone cache recomputed.
 *
 * @param  array<string, mixed>  $attributes
 */
function paidPayment(PaymentLink $link, array $attributes = []): Payment
{
    $payment = Payment::factory()->for($link, 'link')
        ->settled('paid', ['paid_at' => now()])
        ->create(['metadata' => ['payment_intent_id' => 'pi_test_1'], ...$attributes]);

    if ($link->milestone_id !== null) {
        app(RecomputeMilestonePaid::class)->handle($link->milestone_id);
    }

    return $payment->refresh();
}

/**
 * Fake Stripe's refund API: POST /v1/refunds answers $status (or an HTTP error code); GET /v1/refunds/{id} answers $retrieved.
 */
function fakeStripeRefunds(string|int $status = 'succeeded', string $retrieved = 'succeeded'): void
{
    Http::fake(function (Request $request) use ($status, $retrieved) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($request->method() === 'POST' && $path === '/v1/refunds') {
            return is_int($status)
                ? Http::response(['error' => ['code' => 'charge_disputed']], $status)
                : Http::response(['id' => (int) $request['amount'] === 150_000 ? 're_test_1' : 're_test_'.$request['amount'], 'object' => 'refund', 'status' => $status, 'amount' => (int) $request['amount']]);
        }

        if ($request->method() === 'GET' && $path === '/v1/refunds/re_test_1') {
            return Http::response(['id' => 're_test_1', 'object' => 'refund', 'status' => $retrieved, 'amount' => 150_000]);
        }

        return Http::response(['error' => ['code' => 'unexpected']], 404);
    });
}

function requestRefund(User $user, Payment $payment, string $amount, string $reason = 'Client cancelled the milestone'): TestResponse
{
    return test()->actingAs($user)->post(route('admin.payments.refunds.store', $payment), ['amount' => $amount, 'reason' => $reason]);
}

test('a project manager requests a refund and a finance manager approves it through Stripe', function () {
    fakeStripeRefunds();

    requestRefund($this->manager, $this->payment, '1500')->assertRedirect(route('admin.payments.show', $this->payment))->assertSessionHasNoErrors();

    $refund = Refund::query()->sole();

    expect($refund->status)->toBe(RefundStatus::Requested)
        ->and($refund->reference)->toStartWith('RF-')
        ->and($refund->amount_minor)->toBe(150_000);
    Notification::assertSentTo([$this->finance, $this->financeTwo], RefundRequested::class);
    Http::assertNothingSent();

    $this->actingAs($this->finance)->get(route('admin.refunds.index'))
        ->assertSuccessful()
        ->assertSee('Refund awaiting finance approval')
        ->assertSee($refund->reference);

    $this->actingAs($this->finance)->post(route('admin.refunds.approve', $refund))->assertRedirect();

    $refund->refresh();
    $this->payment->refresh();

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->decided_by)->toBe($this->finance->id)
        ->and($refund->gateway_refund_id)->toBe('re_test_1')
        ->and($this->payment->status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->refunded_minor)->toBe(150_000)
        ->and($this->payment->events()->get()->last()->source)->toBe(PaymentEventSource::Refund)
        ->and($this->milestone->refresh()->paid_minor)->toBe(0)
        ->and(CreditNote::query()->sole()->number)->toBe('CN-'.now()->year.'-00001');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.stripe.com/v1/refunds'
        && $request['payment_intent'] === 'pi_test_1' && (int) $request['amount'] === 150_000);

    Notification::assertSentTo($this->client, RefundStatusChanged::class, fn (RefundStatusChanged $notification): bool => $notification->forClient);
    Notification::assertSentTo($this->manager, RefundStatusChanged::class);

    expect(AuditLog::query()->whereIn('operation', ['refund.requested', 'refund.approved', 'refund.succeeded'])->count())->toBe(3);

    $this->actingAs($this->finance)->get(route('admin.credit-notes.print', CreditNote::query()->sole()))
        ->assertSuccessful()->assertSee('CN-'.now()->year.'-00001')->assertSee('Client cancelled the milestone');
});

test('the requester can never approve their own refund', function () {
    fakeStripeRefunds();
    requestRefund($this->finance, $this->payment, '100')->assertSessionHasNoErrors();
    $refund = Refund::query()->sole();

    Notification::assertNotSentTo($this->finance, RefundRequested::class);

    $this->actingAs($this->finance)->post(route('admin.refunds.approve', $refund))->assertForbidden();
    $this->actingAs($this->finance)->post(route('admin.refunds.reject', $refund), ['note' => 'Changed my mind'])->assertForbidden();
    $this->actingAs($this->finance)->get(route('admin.payments.show', $this->payment))->assertSuccessful()->assertSee('Waiting for another finance approver');

    expect($refund->refresh()->status)->toBe(RefundStatus::Requested);
    Http::assertNothingSent();

    $this->actingAs($this->financeTwo)->post(route('admin.refunds.approve', $refund))->assertRedirect();
    expect($refund->refresh()->status)->toBe(RefundStatus::Succeeded);
});

test('staff without refund approval cannot approve and staff without payment access cannot request', function () {
    requestRefund($this->manager, $this->payment, '100');
    $refund = Refund::query()->sole();
    $otherManager = User::factory()->role(Role::ProjectManager)->create();
    $developer = User::factory()->role(Role::Developer)->create();

    $this->actingAs($otherManager)->post(route('admin.refunds.approve', $refund))->assertForbidden();
    requestRefund($developer, $this->payment, '100')->assertForbidden();
    $this->actingAs($developer)->get(route('admin.refunds.index'))->assertForbidden();

    expect(Refund::query()->count())->toBe(1);
});

test('refund requests are capped at the refundable remainder, including open requests', function () {
    requestRefund($this->manager, $this->payment, '1500.01')->assertSessionHasErrors('amount', errorBag: 'refund');
    requestRefund($this->manager, $this->payment, '1000')->assertSessionHasNoErrors();
    requestRefund($this->manager, $this->payment, '600')->assertSessionHasErrors('amount', errorBag: 'refund');
    requestRefund($this->manager, $this->payment, '500')->assertSessionHasNoErrors();
    requestRefund($this->manager, $this->payment, '0.01')->assertSessionHasErrors('amount', errorBag: 'refund');

    expect(Refund::query()->sum('amount_minor'))->toEqual(150_000)
        ->and($this->payment->refresh()->refundableMinor())->toBe(0);

    $pending = Payment::factory()->for($this->link, 'link')->create();
    requestRefund($this->manager, $pending, '10')->assertForbidden();
});

test('partial refunds move the payment to partially refunded and number credit notes without gaps', function () {
    fakeStripeRefunds();

    requestRefund($this->manager, $this->payment, '500');
    $this->actingAs($this->finance)->post(route('admin.refunds.approve', Refund::query()->sole()));

    $this->payment->refresh();
    expect($this->payment->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($this->payment->refunded_minor)->toBe(50_000)
        ->and($this->payment->collectedMinor())->toBe(100_000)
        ->and($this->milestone->refresh()->paid_minor)->toBe(100_000);

    requestRefund($this->manager, $this->payment, '1000.01')->assertSessionHasErrors('amount', errorBag: 'refund');
    requestRefund($this->manager, $this->payment, '1000');
    $this->actingAs($this->finance)->post(route('admin.refunds.approve', Refund::query()->where('status', RefundStatus::Requested)->sole()));

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->refunded_minor)->toBe(150_000)
        ->and(CreditNote::query()->orderBy('id')->pluck('number')->all())->toBe(['CN-'.now()->year.'-00001', 'CN-'.now()->year.'-00002']);
});

test('a refund the gateway refuses fails without touching the ledger and releases the amount', function () {
    fakeStripeRefunds(402);

    requestRefund($this->manager, $this->payment, '1500');
    $refund = Refund::query()->sole();
    $this->actingAs($this->finance)->post(route('admin.refunds.approve', $refund))->assertRedirect();

    expect($refund->refresh()->status)->toBe(RefundStatus::Failed)
        ->and($refund->failure_reason)->toContain('HTTP 402')
        ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->payment->refunded_minor)->toBe(0)
        ->and($this->payment->refundableMinor())->toBe(150_000)
        ->and(CreditNote::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('operation', 'refund.failed')->sole()->result)->toBe('failure');

    Notification::assertSentTo($this->manager, RefundStatusChanged::class);
    Notification::assertNotSentTo($this->client, RefundStatusChanged::class);
});

test('a rejected refund needs a note and notifies the requester', function () {
    requestRefund($this->manager, $this->payment, '100');
    $refund = Refund::query()->sole();

    $this->actingAs($this->finance)->post(route('admin.refunds.reject', $refund), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($this->finance)->post(route('admin.refunds.reject', $refund), ['note' => 'Outside the refund policy'])->assertRedirect();

    expect($refund->refresh()->status)->toBe(RefundStatus::Rejected)
        ->and($refund->decision_note)->toBe('Outside the refund policy');
    Notification::assertSentTo($this->manager, RefundStatusChanged::class);

    $this->actingAs($this->financeTwo)->post(route('admin.refunds.approve', $refund))->assertForbidden();
});

test('a pending Stripe refund completes once, from its webhook confirmed through the API', function () {
    fakeStripeRefunds('pending', 'succeeded');

    requestRefund($this->manager, $this->payment, '1500');
    $refund = Refund::query()->sole();
    $this->actingAs($this->finance)->post(route('admin.refunds.approve', $refund));

    expect($refund->refresh()->status)->toBe(RefundStatus::Processing)
        ->and($refund->gateway_refund_id)->toBe('re_test_1')
        ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Paid);

    $event = ['id' => 'evt_refund_1', 'type' => 'refund.updated', 'data' => ['object' => ['id' => 're_test_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 150_000]]];

    postStripeWebhook($this, $event)->assertOk()->assertJson(['status' => 'processed']);
    postStripeWebhook($this, $event)->assertOk()->assertJson(['status' => 'duplicate']);
    postStripeWebhook($this, [...$event, 'id' => 'evt_refund_2'])->assertOk()->assertJson(['status' => 'processed']);

    expect($refund->refresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($this->payment->refunded_minor)->toBe(150_000)
        ->and(CreditNote::query()->count())->toBe(1);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_ends_with($request->url(), '/v1/refunds/re_test_1'));
});

test('Fawry outlet payments need a manual refund recorded with its external reference', function () {
    configureFawry();
    Http::fake();
    $fawryLink = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 100_000, 'currency' => 'USD', 'gateways' => ['fawry']]);
    $payment = paidPayment($fawryLink, ['gateway' => 'fawry', 'metadata' => ['fawry_payment_method' => 'PAYATFAWRY', 'fawry_reference_number' => '963455678']]);

    requestRefund($this->manager, $payment, '1000');
    $refund = Refund::query()->sole();
    $this->actingAs($this->finance)->post(route('admin.refunds.approve', $refund))->assertRedirect();

    expect($refund->refresh()->status)->toBe(RefundStatus::ManualRequired)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Paid);
    Http::assertNothingSent();

    $this->actingAs($this->finance)->get(route('admin.refunds.index'))->assertSee('Manual refund required')->assertSee('Record manual refund');
    $this->actingAs($this->manager)->post(route('admin.refunds.complete', $refund), ['external_reference' => 'BANK-123'])->assertForbidden();
    $this->actingAs($this->finance)->post(route('admin.refunds.complete', $refund), ['external_reference' => ''])->assertSessionHasErrors('external_reference');
    $this->actingAs($this->finance)->post(route('admin.refunds.complete', $refund), ['external_reference' => 'BANK-TRX 2026/118'])->assertRedirect();

    expect($refund->refresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->manual_reference)->toBe('BANK-TRX 2026/118')
        ->and($refund->completed_by)->toBe($this->finance->id)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(CreditNote::query()->sole()->refund_id)->toBe($refund->id);
});

test('Paymob, PayTabs and Fawry card refunds go through their refund APIs', function (string $gateway, array $metadata, string $gatewayPaymentId, Closure $fake, Closure $assertSent) {
    match ($gateway) {
        'paymob' => configurePaymob(),
        'paytabs' => configurePayTabs(),
        'fawry' => configureFawry(),
    };
    $fake();

    $link = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 100_000, 'gateways' => [$gateway]]);
    $payment = paidPayment($link, ['gateway' => $gateway, 'gateway_payment_id' => $gatewayPaymentId, 'metadata' => $metadata]);

    requestRefund($this->manager, $payment, '400');
    $this->actingAs($this->finance)->post(route('admin.refunds.approve', Refund::query()->sole()));

    expect(Refund::query()->sole()->status)->toBe(RefundStatus::Succeeded)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($payment->refunded_minor)->toBe(40_000);

    Http::assertSent($assertSent);
})->with([
    'paymob' => ['paymob', ['transaction_id' => '192036465', 'paymob_source_type' => 'card'], '217503754',
        fn () => Http::fake(['accept.paymob.com/api/acceptance/void_refund/refund' => Http::response(['id' => 192099999, 'success' => true, 'pending' => false, 'amount_cents' => 40_000])]),
        fn (Request $request): bool => $request->hasHeader('Authorization', 'Token egy_sk_test_codefanz') && $request['transaction_id'] === 192036465 && $request['amount_cents'] === 40_000],
    'paytabs' => ['paytabs', [], 'TST2231500000001',
        fn () => Http::fake(['secure-egypt.paytabs.com/payment/request' => Http::response(['tran_ref' => 'TST2231500000099', 'tran_type' => 'Refund', 'cart_amount' => '400.00', 'payment_result' => ['response_status' => 'A']])]),
        fn (Request $request): bool => $request['tran_type'] === 'refund' && $request['tran_ref'] === 'TST2231500000001' && (string) $request['cart_amount'] === '400'],
    'fawry card' => ['fawry', ['fawry_payment_method' => 'CARD', 'fawry_reference_number' => '963455678'], 'merchant-ref',
        fn () => Http::fake(['atfawry.fawrystaging.com/ECommerceWeb/Fawry/payments/refund' => Http::response(['statusCode' => 200, 'statusDescription' => 'Operation done successfully'])]),
        fn (Request $request): bool => $request['referenceNumber'] === '963455678'
            && $request['signature'] === hash('sha256', 'FAWRYMERCHANT1'.'963455678'.'400.00'.$request['reason'].'fawry-secure-key')],
]);

test('the refunds tab and payment page render in Arabic, right to left', function () {
    requestRefund($this->manager, $this->payment, '100');
    $this->finance->forceFill(['locale' => 'ar'])->save();

    $this->actingAs($this->finance)->get(route('admin.refunds.index'))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee(__('Awaiting finance approval', [], 'ar'))
        ->assertSee(__('Approve', [], 'ar'));

    $this->actingAs($this->finance)->get(route('admin.payments.show', $this->payment))
        ->assertSuccessful()
        ->assertSee(__('Still refundable', [], 'ar'));
});

test('payment links reject gateways that cannot take the currency', function () {
    configurePaymob();

    $this->actingAs($this->manager)->post(route('admin.projects.payment-links.store', $this->project), [
        'milestone' => $this->milestone->ulid,
        'amount' => '100.00',
        'currency' => 'USD',
        'usage' => 'single',
        'gateways' => ['stripe', 'paymob'],
    ])->assertSessionHasErrors('gateways', errorBag: 'paymentLink');

    expect(PaymentLink::query()->count())->toBe(1);
});
