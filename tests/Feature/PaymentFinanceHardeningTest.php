<?php

use App\Actions\Payments\VerifyPayment;
use App\Enums\CustomerUserRole;
use App\Enums\PaymentEventSource;
use App\Enums\Role;
use App\Events\PaymentSucceeded;
use App\Http\Controllers\Admin\PaymentController;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();
    $this->finance = User::factory()->role(Role::FinanceManager)->withConfirmedTwoFactor()->create();
});

function confirmPassword(): void
{
    test()->withSession(['auth.password_confirmed_at' => time()]);
}

test('payment settings require a confirmed password, other settings do not', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'payments'))->assertRedirect(route('password.confirm'));
    $this->actingAs($this->admin)->put(route('admin.settings.update', 'payments'), ['settings' => ['stripe_secret_key' => 'sk_test_abc123']])->assertRedirect(route('password.confirm'));
    $this->actingAs($this->admin)->post(route('admin.settings.payments.test', 'stripe'))->assertRedirect(route('password.confirm'));
    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'general'))->assertSuccessful();

    expect(app(Settings::class)->has('payments', 'stripe_secret_key'))->toBeFalse();

    confirmPassword();
    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'payments'))
        ->assertSuccessful()
        ->assertSee('Paymob')->assertSee('PayTabs')->assertSee('Fawry')
        ->assertSee(route('webhooks.payments', 'fawry'));
});

test('finance staff without settings.manage cannot change gateway credentials', function () {
    confirmPassword();

    $this->actingAs($this->finance)->get(route('admin.settings.edit', 'payments'))->assertForbidden();
    $this->actingAs($this->finance)->put(route('admin.settings.update', 'payments'), ['settings' => ['paymob_hmac_secret' => 'ABC123']])->assertForbidden();
});

test('gateway credential changes are audited without the secret values', function () {
    confirmPassword();

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'payments'), ['settings' => [
        'paytabs_profile_id' => '98765', 'paytabs_server_key' => 'SJNPAYTABSKEY-123', 'paytabs_region' => 'SAU',
    ]])->assertRedirect()->assertSessionHasNoErrors();

    $audit = AuditLog::query()->where('operation', 'settings.payments.updated')->sole();

    expect($audit->after['paytabs_server_key'])->toBe('[secret updated]')
        ->and(json_encode($audit->after).json_encode($audit->before))->not->toContain('SJNPAYTABSKEY-123')
        ->and(app(Settings::class)->get('payments', 'paytabs_server_key'))->toBe('SJNPAYTABSKEY-123');

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'payments'), ['settings' => ['paymob_integration_ids' => '12, abc']])->assertSessionHasErrors('settings.paymob_integration_ids');
});

test('a successful connection test shows Connected with its date until the credentials change', function () {
    configureStripe();
    confirmPassword();
    Http::fake(['api.stripe.com/v1/balance' => Http::response(['object' => 'balance', 'available' => []])]);

    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'payments'))->assertSee('Sandbox · test mode')->assertDontSee('Connected (verified');

    $this->actingAs($this->admin)->post(route('admin.settings.payments.test', 'stripe'))->assertRedirect()->assertSessionHas('toast', 'Stripe connection verified.');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === 'https://api.stripe.com/v1/balance' && $request->hasHeader('Authorization', 'Bearer sk_test_codefanz123'));

    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'payments'))
        ->assertSee('Connected (verified '.now()->translatedFormat('j M Y'), false)
        ->assertSee('data-gateway-status="connected"', false);

    expect(AuditLog::query()->where('operation', 'settings.payments.connection_tested')->sole()->result)->toBe('success');

    $this->actingAs($this->admin)->put(route('admin.settings.update', 'payments'), ['settings' => ['stripe_secret_key' => 'sk_test_newkey456']]);

    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'payments'))->assertDontSee('Connected (verified')->assertSee('Sandbox · test mode');
});

test('a failed connection test never shows Connected', function () {
    configurePaymob();
    confirmPassword();
    Http::fake(['accept.paymob.com/api/auth/tokens' => Http::response(['detail' => 'Incorrect credentials'], 403)]);

    $this->actingAs($this->admin)->post(route('admin.settings.payments.test', 'paymob'))->assertRedirect()->assertSessionHas('toast', 'Paymob connection failed: HTTP 403');

    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'payments'))->assertDontSee('data-gateway-status="connected"', false);
    expect(AuditLog::query()->where('operation', 'settings.payments.connection_tested')->sole()->result)->toBe('failure');
});

test('the connection test of an unconfigured gateway makes no call', function () {
    confirmPassword();
    Http::fake();

    $this->actingAs($this->admin)->post(route('admin.settings.payments.test', 'fawry'))->assertSessionHas('toast', 'Save every Fawry credential before testing the connection.');
    $this->actingAs($this->admin)->post(route('admin.settings.payments.test', 'paypal'))->assertNotFound();

    Http::assertNothingSent();
});

test('the transactions CSV export is streamed with formula-safe cells', function () {
    $customer = Customer::factory()->create(['company_name' => '=HYPERLINK("http://evil.test","x")']);
    $project = Project::factory()->forCustomer($customer)->create(['name' => '+cmd|calc']);
    $link = PaymentLink::factory()->create(['project_id' => $project->id, 'amount_minor' => 123_456]);
    $payment = Payment::factory()->for($link, 'link')->settled('paid', ['paid_at' => now(), 'fee_minor' => 3_000, 'net_minor' => 120_456])->create();
    Payment::factory()->for($link, 'link')->create();

    $response = $this->actingAs($this->finance)->get(route('admin.payments.export', ['status' => 'paid']));
    $response->assertSuccessful()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    $rows = array_map(fn (string $line): array => str_getcsv($line, escape: ''), array_filter(explode("\n", trim(ltrim($csv, "\u{FEFF}")))));

    expect($rows)->toHaveCount(2)
        ->and($rows[1][0])->toBe($payment->reference)
        ->and($rows[1][2])->toBe("'=HYPERLINK(\"http://evil.test\",\"x\")")
        ->and($rows[1][3])->toBe("'+cmd|calc")
        ->and($rows[1][9])->toBe('1234.56')
        ->and($rows[1][10])->toBe('30.00');

    expect(PaymentController::csvCell('-1+2'))->toBe("'-1+2")
        ->and(PaymentController::csvCell('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(PaymentController::csvCell("\tx"))->toBe("'\tx")
        ->and(PaymentController::csvCell('12.50'))->toBe('12.50')
        ->and(AuditLog::query()->where('operation', 'payments.exported')->count())->toBe(1);
});

test('staff without payment access cannot export transactions', function () {
    $developer = User::factory()->role(Role::Developer)->create();

    $this->actingAs($developer)->get(route('admin.payments.export'))->assertForbidden();
});

test('issued invoices and receipts keep the seller details from the moment they were issued', function () {
    Event::fake([PaymentSucceeded::class]);
    $settings = app(Settings::class);
    $settings->set('general', 'company_name', ['en' => 'CodeFanz LLC', 'ar' => 'كودفانز']);
    $settings->set('contact', 'email', 'billing@codefanz.test');

    $customer = Customer::factory()->create();
    User::factory()->forCustomer($customer, CustomerUserRole::Owner)->create();
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($this->finance)->post(route('admin.invoices.issue', $invoice))->assertRedirect();

    expect($invoice->refresh()->seller_snapshot['name'])->toEqual(['en' => 'CodeFanz LLC', 'ar' => 'كودفانز'])
        ->and($invoice->seller_snapshot['email'])->toBe('billing@codefanz.test');

    configureStripe();
    fakeStripe(['amount_total' => 150_000]);
    $project = Project::factory()->forCustomer($customer)->create();
    $link = PaymentLink::factory()->create(['project_id' => $project->id]);
    $payment = Payment::factory()->for($link, 'link')->create(['gateway_payment_id' => 'cs_test_1'])->refresh();
    app(VerifyPayment::class)->handle($payment, PaymentEventSource::Admin);

    $settings->set('general', 'company_name', ['en' => 'Renamed Holding', 'ar' => 'اسم جديد']);
    $settings->set('contact', 'email', 'new@codefanz.test');

    $this->actingAs($this->finance)->get(route('admin.invoices.print', $invoice))
        ->assertSuccessful()->assertSee('CodeFanz LLC')->assertSee('billing@codefanz.test')->assertDontSee('Renamed Holding');
    $this->actingAs($this->finance)->get(route('admin.invoices.print', $invoice).'?lang=ar')
        ->assertSuccessful()->assertSee('كودفانز')->assertSee('dir="rtl"', false);
    $this->actingAs($this->finance)->get(route('admin.payments.receipt', $payment))
        ->assertSuccessful()->assertSee('CodeFanz LLC')->assertDontSee('Renamed Holding');

    // Drafts still follow the live settings until they are issued.
    $draft = Invoice::factory()->create(['customer_id' => $customer->id]);
    $this->actingAs($this->finance)->get(route('admin.invoices.print', $draft))->assertSee('Renamed Holding');
});
