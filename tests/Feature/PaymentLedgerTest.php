<?php

use App\Actions\Payments\VerifyPayment;
use App\Enums\CustomerUserRole;
use App\Enums\MilestoneStatus;
use App\Enums\PaymentEventSource;
use App\Enums\PaymentLinkStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Events\PaymentSucceeded;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\PaymentReceived;
use App\Services\Money;
use App\Services\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Event::fake([PaymentSucceeded::class]);
    configureStripe();

    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);
    $this->client = User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create();
    $this->finance = User::factory()->role(Role::FinanceManager)->withConfirmedTwoFactor()->create();
    $this->project = Project::factory()->forCustomer($this->customer)->create(['name' => 'ClinicFlow']);
    $this->milestone = Milestone::factory()->for($this->project)->status(MilestoneStatus::InProgress)->create(['title' => 'Frontend development', 'amount_minor' => 450_000]);
    $this->link = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 150_000, 'description' => 'Second installment']);
});

/**
 * A pending payment whose Stripe checkout session is cs_test_1.
 */
function pendingStripePayment(PaymentLink $link): Payment
{
    return Payment::factory()->for($link, 'link')->create(['gateway_payment_id' => 'cs_test_1'])->refresh();
}

test('starting a checkout creates a pending payment and a Stripe Checkout Session with the fixed amount', function () {
    fakeStripe();

    $this->post(route('pay.checkout', $this->link), ['gateway' => 'stripe', 'amount' => '1'])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

    $payment = Payment::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway_payment_id)->toBe('cs_test_1')
        ->and($payment->amount_minor)->toBe(150_000)
        ->and($payment->milestone_id)->toBe($this->milestone->id)
        ->and($payment->reference)->toStartWith('PAY-')
        ->and($payment->events()->sole()->source)->toBe(PaymentEventSource::Checkout);

    Http::assertSent(function (Request $request) use ($payment): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
            && $request->hasHeader('Authorization', 'Bearer sk_test_codefanz123')
            && $request->hasHeader('Idempotency-Key', 'checkout-'.$payment->ulid)
            && $request->hasHeader('Stripe-Version', '2024-06-20')
            && $request['mode'] === 'payment'
            && $request['client_reference_id'] === $payment->ulid
            && $request['line_items'][0]['price_data']['currency'] === 'usd'
            && (int) $request['line_items'][0]['price_data']['unit_amount'] === 150_000
            && (int) $request['line_items'][0]['quantity'] === 1
            && $request['metadata']['payment_ulid'] === $payment->ulid
            && $request['success_url'] === route('pay.return', [$this->link, $payment])
            && str_contains($request['cancel_url'], route('pay.show', $this->link));
    });
});

test('checkout is refused for gateways that are not configured or not allowed on the link', function () {
    fakeStripe();
    app(Settings::class)->forget('payments', 'stripe_webhook_secret');

    $this->get(route('pay.show', $this->link))->assertSuccessful()->assertSee('Online payment is not available right now');
    $this->from(route('pay.show', $this->link))->post(route('pay.checkout', $this->link), ['gateway' => 'stripe'])->assertSessionHasErrors('gateway');
    $this->post(route('pay.checkout', $this->link), ['gateway' => 'paypal'])->assertSessionHasErrors('gateway');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();
});

test('a gateway outage while creating the checkout fails the attempt honestly', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 'api_error']], 500)]);

    $this->from(route('pay.show', $this->link))->post(route('pay.checkout', $this->link), ['gateway' => 'stripe'])
        ->assertRedirect(route('pay.show', $this->link))
        ->assertSessionHasErrors('gateway');

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and(Payment::query()->sole()->failure_reason)->toBe('checkout_failed');
});

test('a signed webhook is verified with the Stripe API before the payment is marked paid', function () {
    fakeStripe();
    $payment = pendingStripePayment($this->link);

    postStripeWebhook($this, stripeEvent('checkout.session.completed'))->assertOk()->assertJson(['status' => 'processed']);

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->fee_minor)->toBe(4380)
        ->and($payment->net_minor)->toBe(145_620)
        ->and($payment->verified_via)->toBe(PaymentEventSource::Webhook)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->metadata)->toMatchArray(['payment_intent_id' => 'pi_test_1', 'payment_method_type' => 'card'])
        ->and(json_encode($payment->metadata))->not->toContain('4242')
        ->and($this->link->fresh()->status)->toBe(PaymentLinkStatus::Used)
        ->and($this->milestone->fresh()->paid_minor)->toBe(150_000)
        ->and($payment->receipt->number)->toBe(sprintf('RCP-%d-00001', now()->year));

    $event = WebhookEvent::query()->sole();
    expect($event->signature_valid)->toBeTrue()
        ->and($event->processed_at)->not->toBeNull()
        ->and(json_encode($event->payload))->not->toContain('payer@example.test');

    // The factory skips StartCheckout, so the only transition is the verified one.
    expect($payment->events()->pluck('to_status')->map->value->all())->toBe(['paid'])
        ->and($payment->events()->latest('id')->first()->webhook_event_id)->toBe($event->id);

    expect(AuditLog::query()->where('operation', 'payment.verified')->where('auditable_id', $payment->id)->exists())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/v1/checkout/sessions/cs_test_1'));
    Notification::assertSentTo($this->client, PaymentReceived::class, fn (PaymentReceived $notification): bool => $notification->forClient);
    Notification::assertSentTo($this->finance, PaymentReceived::class, fn (PaymentReceived $notification): bool => ! $notification->forClient);
    Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event): bool => $event->payment->is($payment));
});

test('webhooks with a bad signature, no signature or an old timestamp are rejected without touching the ledger', function (Closure $send) {
    fakeStripe();
    $payment = pendingStripePayment($this->link);

    $send($this)->assertStatus(400)->assertJson(['status' => 'invalid']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Receipt::count())->toBe(0);

    $event = WebhookEvent::query()->sole();
    expect($event->signature_valid)->toBeFalse()
        ->and($event->event_id)->toStartWith('unverified-')
        ->and($event->processed_at)->toBeNull();

    Http::assertNothingSent();
})->with([
    'wrong secret' => [fn ($test) => postStripeWebhook($test, stripeEvent('checkout.session.completed'), secret: 'whsec_attacker')],
    'tampered signature' => [fn ($test) => postStripeWebhook($test, stripeEvent('checkout.session.completed'), signature: str_repeat('a', 64))],
    'timestamp too old' => [fn ($test) => postStripeWebhook($test, stripeEvent('checkout.session.completed'), timestamp: now()->subMinutes(10)->getTimestamp())],
    'timestamp in the future' => [fn ($test) => postStripeWebhook($test, stripeEvent('checkout.session.completed'), timestamp: now()->addMinutes(10)->getTimestamp())],
    'no signature header' => [fn ($test) => $test->postJson(route('webhooks.payments', 'stripe'), stripeEvent('checkout.session.completed'))],
]);

test('a rejected delivery cannot reserve the event id of a later genuine delivery', function () {
    fakeStripe();
    $payment = pendingStripePayment($this->link);

    postStripeWebhook($this, stripeEvent('checkout.session.completed'), secret: 'whsec_attacker')->assertStatus(400);
    postStripeWebhook($this, stripeEvent('checkout.session.completed'))->assertOk()->assertJson(['status' => 'processed']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('duplicate and replayed webhook deliveries are no-ops', function () {
    fakeStripe();
    $payment = pendingStripePayment($this->link);

    postStripeWebhook($this, stripeEvent('checkout.session.completed'))->assertOk()->assertJson(['status' => 'processed']);
    postStripeWebhook($this, stripeEvent('checkout.session.completed'))->assertOk()->assertJson(['status' => 'duplicate']);
    // A different event about the same, already paid session.
    postStripeWebhook($this, stripeEvent('checkout.session.async_payment_succeeded', eventId: 'evt_test_2'))->assertOk()->assertJson(['status' => 'processed']);

    expect(WebhookEvent::count())->toBe(2)
        ->and(Receipt::count())->toBe(1)
        ->and($payment->events()->where('to_status', 'paid')->count())->toBe(1)
        ->and($this->milestone->fresh()->paid_minor)->toBe(150_000);

    Http::assertSentCount(1);
    Notification::assertSentToTimes($this->client, PaymentReceived::class, 1);
});

test('a webhook that cannot reach the gateway API asks for a retry and is processed on redelivery', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::sequence()
        ->push(['error' => ['code' => 'api_error']], 503)
        ->push(stripeSession())]);
    $payment = pendingStripePayment($this->link);

    postStripeWebhook($this, stripeEvent('checkout.session.completed'))->assertStatus(503);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(WebhookEvent::query()->sole()->processed_at)->toBeNull();

    postStripeWebhook($this, stripeEvent('checkout.session.completed'))->assertOk()->assertJson(['status' => 'processed']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and(WebhookEvent::query()->sole()->attempts)->toBe(2);
});

test('events about unknown sessions or other objects are recorded and ignored', function () {
    fakeStripe();

    postStripeWebhook($this, stripeEvent('checkout.session.completed', sessionId: 'cs_unknown'))->assertOk()->assertJson(['status' => 'ignored']);
    postStripeWebhook($this, ['id' => 'evt_other', 'type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1', 'object' => 'customer']]])->assertOk()->assertJson(['status' => 'ignored']);

    expect(WebhookEvent::count())->toBe(2)->and(Payment::count())->toBe(0);
    Http::assertNothingSent();
});

test('an async payment failure marks the payment failed without a receipt', function () {
    fakeStripe(['payment_status' => 'unpaid', 'payment_intent' => ['status' => 'requires_payment_method', 'last_payment_error' => ['code' => 'card_declined']]]);
    $payment = pendingStripePayment($this->link);

    postStripeWebhook($this, stripeEvent('checkout.session.async_payment_failed', object: ['payment_status' => 'unpaid']))->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('card_declined')
        ->and($payment->receipt)->toBeNull()
        ->and($this->link->fresh()->status)->toBe(PaymentLinkStatus::Active)
        ->and($this->milestone->fresh()->paid_minor)->toBe(0);
});

test('the return URL alone never marks a payment paid', function (array|int $gatewayAnswer) {
    fakeStripe($gatewayAnswer);
    $payment = pendingStripePayment($this->link);

    $this->get(route('pay.return', [$this->link, $payment]))
        ->assertSuccessful()
        ->assertSee('We’re confirming your payment')
        ->assertDontSee('Payment confirmed');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Receipt::count())->toBe(0)
        ->and($this->milestone->fresh()->paid_minor)->toBe(0);
})->with([
    'gateway says unpaid' => [['status' => 'open', 'payment_status' => 'unpaid']],
    'gateway unreachable' => [500],
]);

test('the return URL shows the confirmation once the gateway verifies the payment', function () {
    fakeStripe();
    $payment = pendingStripePayment($this->link);

    $this->get(route('pay.return', [$this->link, $payment]))
        ->assertSuccessful()
        ->assertSee('Payment confirmed')
        ->assertSee($payment->fresh()->receipt->number);

    expect($payment->fresh()->verified_via)->toBe(PaymentEventSource::ReturnUrl);
});

test('the return URL only accepts payments of that link', function () {
    $otherLink = PaymentLink::factory()->create();
    $payment = pendingStripePayment($otherLink);

    $this->get(route('pay.return', [$this->link, $payment]))->assertNotFound();
});

test('VerifyPayment locks the payment row and is idempotent', function () {
    fakeStripe();
    $payment = pendingStripePayment($this->link);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $verify = app(VerifyPayment::class);
    $verify->handle($payment, PaymentEventSource::Webhook);
    $again = $verify->handle($payment->fresh(), PaymentEventSource::ReturnUrl);
    // A stale in-memory copy still reports "pending": the locked re-read inside the transaction makes it a no-op.
    $verify->handle($payment, PaymentEventSource::Admin);

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'from `payments`') && str_contains($sql, 'for update')))->toBeTrue()
        ->and($again->status)->toBe(PaymentStatus::Paid)
        ->and($again->verified_via)->toBe(PaymentEventSource::Webhook)
        ->and(PaymentEvent::query()->where('payment_id', $payment->id)->where('to_status', 'paid')->count())->toBe(1)
        ->and(Receipt::count())->toBe(1)
        ->and($this->link->fresh()->uses)->toBe(1);

    Http::assertSentCount(2);
});

test('a gateway amount that does not match the payment is never marked paid', function () {
    fakeStripe(['amount_total' => 100]);
    $payment = pendingStripePayment($this->link);

    app(VerifyPayment::class)->handle($payment, PaymentEventSource::Webhook);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(AuditLog::query()->where('operation', 'payment.verification_mismatch')->where('result', 'failure')->exists())->toBeTrue();
});

test('ledger rows cannot be changed outside the payment actions', function () {
    fakeStripe();
    $payment = pendingStripePayment($this->link);
    postStripeWebhook($this, stripeEvent('checkout.session.completed'));

    expect(fn () => $payment->fresh()->forceFill(['status' => PaymentStatus::Refunded])->save())->toThrow(LogicException::class)
        ->and(fn () => $payment->fresh()->delete())->toThrow(LogicException::class)
        ->and(fn () => PaymentEvent::query()->first()->update(['to_status' => 'failed']))->toThrow(LogicException::class)
        ->and(fn () => PaymentEvent::query()->first()->delete())->toThrow(LogicException::class)
        ->and(fn () => WebhookEvent::query()->first()->update(['payload' => ['forged' => true]]))->toThrow(LogicException::class)
        ->and(fn () => WebhookEvent::query()->first()->delete())->toThrow(LogicException::class)
        ->and(fn () => Receipt::query()->first()->delete())->toThrow(LogicException::class)
        ->and(fn () => $this->link->fresh()->delete())->toThrow(LogicException::class);
});

test('the pay page shows the fixed amount, masks the customer and offers configured gateways', function () {
    $this->get(route('pay.show', $this->link))
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('USD 1,500.00')
        ->assertSee('Cl')
        ->assertDontSee('ClinicPlus')
        ->assertSee('Frontend development')
        ->assertSee('Second installment')
        ->assertSee('value="stripe"', false)
        ->assertSee('Amount set by CodeFanz · cannot be changed');
});

test('the pay page renders in Arabic with RTL and Western digits', function () {
    $this->withSession(['locale' => 'ar'])->get(route('pay.show', $this->link))
        ->assertSuccessful()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('اختر طريقة الدفع')
        ->assertSee('USD 1,500.00');
});

test('expired, used and cancelled links show their state and refuse checkout', function (Closure $state, string $message) {
    fakeStripe();
    $state($this->link);

    $this->get(route('pay.show', $this->link))->assertSuccessful()->assertSee($message)->assertDontSee('value="stripe"', false);
    $this->post(route('pay.checkout', $this->link), ['gateway' => 'stripe'])->assertSessionHasErrors('gateway');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'expired by date' => [fn (PaymentLink $link) => $link->forceFill(['expires_at' => now()->subMinute()])->save(), 'This payment link has expired'],
    'used' => [fn (PaymentLink $link) => $link->forceFill(['status' => PaymentLinkStatus::Used])->save(), 'This payment request has been paid'],
    'cancelled' => [fn (PaymentLink $link) => $link->forceFill(['status' => PaymentLinkStatus::Cancelled])->save(), 'This payment link was cancelled'],
]);

test('unknown link ids are a 404', function () {
    $this->get('/pay/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
});

test('a milestone that is already paid cannot be paid again through an old link', function () {
    fakeStripe();
    $this->milestone->forceFill(['paid_minor' => 400_000])->save();

    $this->post(route('pay.checkout', $this->link), ['gateway' => 'stripe'])->assertSessionHasErrors('gateway');

    expect(Payment::count())->toBe(0);
});

test('payments:reconcile recomputes milestone paid amounts from the ledger', function () {
    Payment::factory()->for($this->link, 'link')->settled('paid')->create();
    $second = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 100_000]);
    Payment::factory()->for($second, 'link')->settled('partially_refunded', ['refunded_minor' => 25_000])->create();
    Payment::factory()->for($second, 'link')->settled('failed')->create();
    $this->milestone->forceFill(['paid_minor' => 999])->save();

    $this->artisan('payments:reconcile')->expectsOutputToContain('corrected 1')->assertSuccessful();

    expect($this->milestone->fresh()->paid_minor)->toBe(150_000 + 75_000)
        ->and(AuditLog::query()->where('operation', 'milestone.paid_reconciled')->exists())->toBeTrue();

    $this->artisan('payments:reconcile')->expectsOutputToContain('corrected 0')->assertSuccessful();
});

test('payments:expire-links expires active links past their date only', function () {
    $this->link->forceFill(['expires_at' => now()->subHour()])->save();
    $open = PaymentLink::factory()->create(['expires_at' => now()->addDay()]);

    $this->artisan('payments:expire-links')->assertSuccessful();

    expect($this->link->fresh()->status)->toBe(PaymentLinkStatus::Expired)
        ->and($open->fresh()->status)->toBe(PaymentLinkStatus::Active);
});

test('money is formatted with Western digits and isolated for right-to-left text', function () {
    expect(Money::localized(450_000, 'usd', 'en'))->toBe('USD 4,500.00')
        ->and(Money::localized(450_050, 'EGP', 'ar'))->toBe("\u{2066}EGP 4,500.50\u{2069}")
        ->and(Money::plain(-1_000, 'USD'))->toBe('-USD 10.00')
        ->and(Money::toMinor('1250.5'))->toBe(125_050);
});
