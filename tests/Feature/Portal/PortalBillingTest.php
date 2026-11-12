<?php

use App\Actions\Billing\IssueInvoice;
use App\Enums\CustomerUserRole;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Notification::fake();

    $this->finance = User::factory()->role(Role::FinanceManager)->withConfirmedTwoFactor()->create();
    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);
    $this->client = User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create(['locale' => 'en']);
    $this->project = Project::factory()->forCustomer($this->customer)->create(['name' => 'ClinicFlow']);
    $this->milestone = Milestone::factory()->for($this->project)->create(['title' => 'Frontend development', 'amount_minor' => 450_000]);
    $this->link = PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 150_000]);

    $this->invoice = Invoice::factory()->create(['customer_id' => $this->customer->id, 'project_id' => $this->project->id, 'milestone_id' => $this->milestone->id, 'payment_link_id' => $this->link->id, 'subtotal_minor' => 150_000, 'total_minor' => 150_000]);
    app(IssueInvoice::class)->handle($this->invoice, $this->finance);
    $this->draft = Invoice::factory()->create(['customer_id' => $this->customer->id]);

    $this->payment = Payment::factory()->for($this->link, 'link')->settled('paid', ['paid_at' => now(), 'verified_at' => now(), 'verified_via' => 'webhook'])->create();
    Receipt::create(['payment_id' => $this->payment->id, 'number' => 'RCP-2026-00001', 'customer_id' => $this->customer->id, 'customer_snapshot' => Invoice::snapshotOf($this->customer), 'amount_minor' => 150_000, 'currency' => 'USD', 'issued_at' => now()]);
    $this->failed = Payment::factory()->for(PaymentLink::factory()->forMilestone($this->milestone)->create(['amount_minor' => 1_000]), 'link')->settled('failed')->create();

    $this->otherCustomer = Customer::factory()->create(['company_name' => 'Other Co']);
    $otherProject = Project::factory()->forCustomer($this->otherCustomer)->create();
    $otherLink = PaymentLink::factory()->create(['project_id' => $otherProject->id, 'customer_id' => $this->otherCustomer->id]);
    $this->otherInvoice = Invoice::factory()->create(['customer_id' => $this->otherCustomer->id]);
    app(IssueInvoice::class)->handle($this->otherInvoice, $this->finance);
    $this->otherPayment = Payment::factory()->for($otherLink, 'link')->settled('paid')->create();
    Receipt::create(['payment_id' => $this->otherPayment->id, 'number' => 'RCP-2026-00002', 'customer_id' => $this->otherCustomer->id, 'customer_snapshot' => Invoice::snapshotOf($this->otherCustomer), 'amount_minor' => 150_000, 'currency' => 'USD', 'issued_at' => now()]);
});

test('clients see their issued invoices with Pay now, never drafts or other customers’ invoices', function () {
    $this->actingAs($this->client)->get(route('portal.invoices.index'))
        ->assertSuccessful()
        ->assertSee($this->invoice->fresh()->number)
        ->assertSee('Pay now')
        ->assertSee(route('pay.show', $this->link))
        ->assertDontSee($this->otherInvoice->fresh()->number)
        ->assertDontSee('data-invoice="'.$this->draft->ulid.'"', false)
        ->assertViewHas('unpaidCount', 1);

    $this->actingAs($this->client)->get(route('portal.invoices.index', ['status' => 'paid']))->assertSuccessful()->assertDontSee($this->invoice->fresh()->number);
});

test('clients open their own invoice and receipt documents; other customers’ and drafts are 404', function () {
    $this->actingAs($this->client)->get(route('portal.invoices.print', $this->invoice))->assertSuccessful()->assertSee('ClinicPlus');
    $this->actingAs($this->client)->get(route('portal.invoices.print', $this->draft))->assertNotFound();
    $this->actingAs($this->client)->get(route('portal.invoices.print', $this->otherInvoice))->assertNotFound();

    $this->actingAs($this->client)->get(route('portal.payments.receipt', $this->payment))->assertSuccessful()->assertSee('RCP-2026-00001')->assertSee('USD 1,500.00');
    $this->actingAs($this->client)->get(route('portal.payments.receipt', $this->otherPayment))->assertNotFound();
    $this->actingAs($this->client)->get(route('portal.payments.receipt', $this->failed))->assertNotFound();
});

test('payment history shows own payments, failed attempts honestly and receipt links', function () {
    $this->actingAs($this->client)->get(route('portal.payments.index'))
        ->assertSuccessful()
        ->assertSee($this->payment->reference)
        ->assertSee($this->failed->reference)
        ->assertSee('Failed')
        ->assertSee('RCP-2026-00001')
        ->assertDontSee($this->otherPayment->reference)
        ->assertDontSee('RCP-2026-00002')
        ->assertSee('A failed attempt is never charged');
});

test('the dashboard lists recent invoices with a Pay button', function () {
    $this->actingAs($this->client)->get(route('portal.dashboard'))
        ->assertSuccessful()
        ->assertSee('data-recent-invoices', false)
        ->assertSee($this->invoice->fresh()->number)
        ->assertDontSee($this->otherInvoice->fresh()->number);
});

test('portal billing pages render in English and Arabic with the right direction', function (string $routeName, Closure $parameters, string $english, string $arabic) {
    foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
        $this->client->forceFill(['locale' => $locale])->save();

        $this->actingAs($this->client)->get(route($routeName, $parameters($this)))
            ->assertSuccessful()
            ->assertSee('<html lang="'.$locale.'" dir="'.$direction.'">', false)
            ->assertSee($locale === 'ar' ? $arabic : $english)
            ->assertSee('USD 1,500.00');
    }
})->with([
    'invoices' => ['portal.invoices.index', fn () => [], 'Invoices', 'الفواتير'],
    'payments' => ['portal.payments.index', fn () => [], 'Payment history', 'سجل المدفوعات'],
    'invoice document' => ['portal.invoices.print', fn ($test) => ['invoice' => $test->invoice], 'Bill to', 'فاتورة إلى'],
    'receipt' => ['portal.payments.receipt', fn ($test) => ['payment' => $test->payment], 'Received from', 'استُلم من'],
]);

test('the public receipt link from the checkout is signed', function () {
    $this->get(route('pay.receipt', [$this->link, $this->payment]))->assertForbidden();

    $url = URL::temporarySignedRoute('pay.receipt', now()->addHour(), ['paymentLink' => $this->link, 'payment' => $this->payment]);
    $this->get($url)->assertSuccessful()->assertSee('RCP-2026-00001');
});
