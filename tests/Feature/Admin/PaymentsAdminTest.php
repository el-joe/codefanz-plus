<?php

use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\MilestoneStatus;
use App\Enums\PaymentLinkStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->manager = User::factory()->role(Role::ProjectManager)->create(['locale' => 'en']);
    $this->finance = User::factory()->role(Role::FinanceManager)->withConfirmedTwoFactor()->create(['locale' => 'en']);
    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus', 'tax_id' => 'EG-123', 'billing_city' => 'Cairo']);
    $this->project = Project::factory()->forCustomer($this->customer)->create(['name' => 'ClinicFlow', 'currency' => 'USD']);
    $this->milestone = Milestone::factory()->for($this->project)->status(MilestoneStatus::InProgress)->create(['title' => 'Frontend development', 'amount_minor' => 450_000]);
});

/**
 * @return array<string, mixed>
 */
function paymentLinkPayload(Milestone $milestone, array $overrides = []): array
{
    return [
        'milestone' => $milestone->ulid,
        'amount' => '4500.00',
        'currency' => 'USD',
        'description' => 'Second installment (50%)',
        'expires_on' => now()->addDays(7)->toDateString(),
        'usage' => 'single',
        'gateways' => ['stripe'],
        ...$overrides,
    ];
}

test('staff without payment permissions get 403 on every finance screen and action', function (Role $role) {
    $user = User::factory()->role($role)->withConfirmedTwoFactor()->create();
    $link = PaymentLink::factory()->forMilestone($this->milestone)->create();
    $payment = Payment::factory()->for($link, 'link')->create();
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($user)->get(route('admin.payments.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.payments.show', $payment))->assertForbidden();
    $this->actingAs($user)->get(route('admin.payment-links.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.invoices.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.invoices.print', $invoice))->assertForbidden();
    $this->actingAs($user)->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone))->assertForbidden();
    $this->actingAs($user)->post(route('admin.payment-links.cancel', $link))->assertForbidden();
    $this->actingAs($user)->post(route('admin.invoices.issue', $invoice))->assertForbidden();

    expect(PaymentLink::count())->toBe(1)->and($link->fresh()->status)->toBe(PaymentLinkStatus::Active);
})->with([
    'developer (projects.manage only)' => [Role::Developer],
    'content manager' => [Role::ContentManager],
    'sales manager' => [Role::SalesManager],
]);

test('clients cannot reach admin payment screens', function () {
    $client = User::factory()->client()->create();

    $this->actingAs($client)->get(route('admin.payments.index'))->assertForbidden();
});

test('a project manager creates a milestone payment link capped at the remaining balance', function () {
    $this->actingAs($this->manager)->get(route('admin.projects.show', ['project' => $this->project, 'tab' => 'milestones']))
        ->assertSuccessful()
        ->assertSee('data-payment-link-dialog', false)
        ->assertSee('USD 4,500.00 remaining')
        ->assertSee('Requires credentials');

    $this->actingAs($this->manager)
        ->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone, ['amount' => '3000', 'usage' => 'reusable']))
        ->assertRedirect(route('admin.projects.show', ['project' => $this->project, 'tab' => 'payments']))
        ->assertSessionHas('createdPaymentLink');

    $link = PaymentLink::query()->sole();
    expect($link->amount_minor)->toBe(300_000)
        ->and($link->single_use)->toBeTrue()
        ->and($link->customer_id)->toBe($this->customer->id)
        ->and($link->reference)->toStartWith('PL-')
        ->and($link->expires_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'payment_link.created')->where('user_id', $this->manager->id)->exists())->toBeTrue();

    // 4,500 − 3,000 already requested = 1,500 left.
    $this->actingAs($this->manager)
        ->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone, ['amount' => '1500.01']))
        ->assertSessionHasErrorsIn('paymentLink', 'amount');

    $this->actingAs($this->manager)
        ->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone, ['amount' => '1500']))
        ->assertSessionHasNoErrors();

    expect(PaymentLink::count())->toBe(2);
});

test('the cap counts collected payments and ignores cancelled or expired links', function () {
    $this->milestone->forceFill(['paid_minor' => 200_000])->save();
    PaymentLink::factory()->forMilestone($this->milestone)->status(PaymentLinkStatus::Cancelled)->create(['amount_minor' => 250_000]);
    PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 250_000, 'expires_at' => now()->subDay()]);

    $this->actingAs($this->manager)
        ->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone, ['amount' => '2500.01']))
        ->assertSessionHasErrorsIn('paymentLink', 'amount');

    $this->actingAs($this->manager)
        ->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone, ['amount' => '2500']))
        ->assertSessionHasNoErrors();
});

test('payment link validation rejects bad input', function (array $overrides, string $field) {
    $otherMilestone = Milestone::factory()->create();

    if ($field === 'milestone') {
        $overrides['milestone'] = $otherMilestone->ulid;
    }

    $this->actingAs($this->manager)
        ->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone, $overrides))
        ->assertSessionHasErrorsIn('paymentLink', $field);

    expect(PaymentLink::count())->toBe(0);
})->with([
    'zero amount' => [['amount' => '0'], 'amount'],
    'three decimals' => [['amount' => '10.555'], 'amount'],
    'unsupported currency' => [['currency' => 'KWD'], 'currency'],
    'milestone currency mismatch' => [['currency' => 'EUR'], 'currency'],
    'expiry in the past' => [['expires_on' => '2020-01-01'], 'expires_on'],
    'unknown gateway' => [['gateways' => ['paypal']], 'gateways.0'],
    'no gateway' => [['gateways' => []], 'gateways'],
    'milestone of another project' => [[], 'milestone'],
]);

test('a custom link without milestone may be reusable', function () {
    $this->actingAs($this->finance)
        ->post(route('admin.projects.payment-links.store', $this->project), paymentLinkPayload($this->milestone, ['milestone' => '', 'amount' => '99999', 'usage' => 'reusable']))
        ->assertSessionHasNoErrors();

    expect(PaymentLink::query()->sole())->milestone_id->toBeNull()->single_use->toBeFalse();
});

test('staff cancel an active link and it is audited', function () {
    $link = PaymentLink::factory()->forMilestone($this->milestone)->create();

    $this->actingAs($this->manager)->post(route('admin.payment-links.cancel', $link))->assertRedirect();

    expect($link->fresh())->status->toBe(PaymentLinkStatus::Cancelled)->cancelled_by->toBe($this->manager->id)
        ->and(AuditLog::query()->where('operation', 'payment_link.cancelled')->exists())->toBeTrue();

    $this->actingAs($this->manager)->post(route('admin.payment-links.cancel', $link))->assertSessionHasErrors('link');
});

test('finance sees KPIs, transactions with fee, net and verification, and payment history', function () {
    $link = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 150_000]);
    $paid = Payment::factory()->for($link, 'link')->settled('paid', ['fee_minor' => 4_380, 'net_minor' => 145_620, 'paid_at' => now(), 'verified_at' => now(), 'verified_via' => 'webhook'])->create();
    Payment::factory()->for(PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 1_000]), 'link')->settled('failed')->create();

    $this->actingAs($this->finance)->get(route('admin.payments.index'))
        ->assertSuccessful()
        ->assertSee('USD 1,500.00')
        ->assertSee('USD 43.80')
        ->assertSee('USD 1,456.20')
        ->assertSee('Webhook + API')
        ->assertSee('Failed')
        ->assertSee('Financial controls')
        ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['collected'] === 'USD 1,500.00' && $kpis['failed'] === 1);

    $this->actingAs($this->finance)->get(route('admin.payments.index', ['status' => 'failed']))->assertSuccessful()->assertDontSee($paid->reference);
    $this->actingAs($this->finance)->get(route('admin.payments.show', $paid))->assertSuccessful()->assertSee($paid->reference)->assertSee('append-only');
    $this->actingAs($this->finance)->get(route('admin.payment-links.index'))->assertSuccessful()->assertSee($link->reference);
});

test('an invoice drafted from a milestone is issued with a sequential number and a frozen customer snapshot', function () {
    $this->actingAs($this->finance)->post(route('admin.projects.milestones.invoice', [$this->project, $this->milestone]))->assertRedirect();
    $invoice = Invoice::query()->sole();

    expect($invoice)->status->toBe(InvoiceStatus::Draft)->number->toBeNull()->total_minor->toBe(450_000);

    $this->actingAs($this->finance)->put(route('admin.invoices.update', $invoice), ['description' => 'Frontend development', 'tax_rate' => '14', 'due_at' => now()->addDays(10)->toDateString(), 'notes' => 'Bank transfer accepted.'])
        ->assertRedirect(route('admin.invoices.show', $invoice));

    expect($invoice->fresh())->tax_minor->toBe(63_000)->total_minor->toBe(513_000);

    $this->actingAs($this->finance)->post(route('admin.invoices.issue', $invoice))->assertRedirect();
    $invoice->refresh();

    expect($invoice->number)->toBe(sprintf('INV-%d-00001', now()->year))
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->customer_snapshot)->toMatchArray(['name' => 'ClinicPlus', 'tax_id' => 'EG-123']);

    $this->customer->update(['company_name' => 'Renamed Corp', 'tax_id' => 'NEW-999']);

    $this->actingAs($this->finance)->get(route('admin.invoices.print', $invoice))
        ->assertSuccessful()
        ->assertSee('ClinicPlus')
        ->assertSee('EG-123')
        ->assertDontSee('Renamed Corp')
        ->assertSee('USD 5,130.00');

    // Issued invoices are immutable: the edit form is refused and direct model changes throw.
    $this->actingAs($this->finance)->put(route('admin.invoices.update', $invoice), ['description' => 'Changed', 'tax_rate' => '0'])->assertSessionHasErrors('invoice');
    expect(fn () => $invoice->fresh()->forceFill(['total_minor' => 1])->save())->toThrow(LogicException::class)
        ->and(fn () => $invoice->fresh()->delete())->toThrow(LogicException::class);

    $this->actingAs($this->finance)->post(route('admin.invoices.issue', $invoice))->assertSessionHasErrors('invoice');
});

test('invoice numbers are sequential and gap-free, even when an issuing transaction rolls back', function () {
    $invoices = Invoice::factory()->count(3)->create(['customer_id' => $this->customer->id]);
    $issue = app(IssueInvoice::class);

    try {
        DB::transaction(function () use ($issue, $invoices): void {
            $issue->handle($invoices[0], $this->finance);

            throw new RuntimeException('Something failed after numbering.');
        });
    } catch (RuntimeException) {
    }

    expect($invoices[0]->fresh()->number)->toBeNull();

    $issue->handle($invoices[1], $this->finance);
    $issue->handle($invoices[0], $this->finance);
    app(VoidInvoice::class)->handle($invoices[0]->fresh(), 'Duplicate', $this->finance);
    $issue->handle($invoices[2], $this->finance);

    $year = now()->year;
    expect($invoices->map(fn (Invoice $invoice) => $invoice->fresh()->number)->all())
        ->toBe(["INV-{$year}-00002", "INV-{$year}-00001", "INV-{$year}-00003"])
        ->and($invoices[0]->fresh()->status)->toBe(InvoiceStatus::Void);
});

test('an invoice drafted from a payment link is paid when that link is paid', function () {
    configureStripe();
    fakeStripe();
    $link = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 150_000]);

    $this->actingAs($this->finance)->post(route('admin.payment-links.invoice', $link))->assertRedirect();
    $invoice = Invoice::query()->sole();
    app(IssueInvoice::class)->handle($invoice, $this->finance);

    Payment::factory()->for($link, 'link')->create(['gateway_payment_id' => 'cs_test_1']);
    postStripeWebhook($this, stripeEvent('checkout.session.completed'))->assertOk();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Paid)->paid_at->not->toBeNull();
});

test('voiding requires a reason and keeps the number', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id]);
    app(IssueInvoice::class)->handle($invoice, $this->finance);

    $this->actingAs($this->finance)->post(route('admin.invoices.void', $invoice), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->finance)->post(route('admin.invoices.void', $invoice), ['reason' => 'Issued twice'])->assertRedirect();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Void)->number->not->toBeNull()->void_reason->toBe('Issued twice');
});

test('invoices render in English and Arabic with the right direction', function () {
    $invoice = Invoice::factory()->create(['customer_id' => $this->customer->id, 'project_id' => $this->project->id]);
    app(IssueInvoice::class)->handle($invoice, $this->finance);

    $this->actingAs($this->finance)->get(route('admin.invoices.print', $invoice))
        ->assertSuccessful()
        ->assertSee('<html lang="en" dir="ltr">', false)
        ->assertSee('Bill to')
        ->assertSee($invoice->fresh()->number);

    $this->actingAs($this->finance)->get(route('admin.invoices.print', ['invoice' => $invoice, 'lang' => 'ar']))
        ->assertSuccessful()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('فاتورة')
        ->assertSee('USD 1,000.00');

    $this->actingAs($this->finance)->get(route('admin.invoices.show', $invoice))->assertSuccessful();
    $this->actingAs($this->finance)->get(route('admin.invoices.index'))->assertSuccessful()->assertSee($invoice->fresh()->number);
});

test('milestones with payment links cannot be deleted or change currency', function () {
    PaymentLink::factory()->forMilestone($this->milestone)->create();

    $this->actingAs($this->manager)->delete(route('admin.projects.milestones.destroy', [$this->project, $this->milestone]))->assertSessionHas('toast');
    expect($this->milestone->fresh())->not->toBeNull();

    $this->actingAs($this->manager)->put(route('admin.projects.milestones.update', [$this->project, $this->milestone]), [
        'title' => 'Frontend development', 'amount' => '4500', 'currency' => 'EUR', 'lock_version' => $this->milestone->lock_version,
    ])->assertSessionHasErrors('currency');
});

test('payment settings show honest gateway statuses', function () {
    $admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();
    $this->withSession(['auth.password_confirmed_at' => time()]);

    $this->actingAs($admin)->get(route('admin.settings.edit', 'payments'))
        ->assertSuccessful()
        ->assertSee('Requires credentials')
        ->assertSee(route('webhooks.payments', 'stripe'))
        ->assertDontSee('Sandbox · test mode');

    $this->actingAs($admin)->put(route('admin.settings.update', 'payments'), ['settings' => [
        'stripe_mode' => 'test', 'stripe_secret_key' => 'sk_test_abc123', 'stripe_webhook_secret' => 'whsec_abc123',
    ]])->assertRedirect();

    $this->actingAs($admin)->get(route('admin.settings.edit', 'payments'))
        ->assertSuccessful()
        ->assertSee('Sandbox · test mode')
        ->assertDontSee('sk_test_abc123')
        ->assertDontSee('whsec_abc123');

    $this->actingAs($admin)->put(route('admin.settings.update', 'payments'), ['settings' => ['stripe_mode' => 'live']]);
    $this->actingAs($admin)->get(route('admin.settings.edit', 'payments'))->assertSee('Live · not verified');

    $this->actingAs($admin)->put(route('admin.settings.update', 'payments'), ['settings' => ['stripe_secret_key' => 'not-a-key']])->assertSessionHasErrors('settings.stripe_secret_key');
});
