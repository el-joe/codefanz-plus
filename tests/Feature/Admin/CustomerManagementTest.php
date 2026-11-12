<?php

use App\Enums\CustomerStatus;
use App\Enums\CustomerUserRole;
use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\ClientInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();

    $this->sales = User::factory()->role(Role::SalesManager)->create(['locale' => 'en']);
});

/**
 * @return array<string, mixed>
 */
function customerPayload(array $overrides = []): array
{
    return [
        'type' => 'company',
        'status' => 'active',
        'company_name' => 'ClinicPlus',
        'contact_name' => 'Sara Ahmed',
        'email' => 'Sara@ClinicPlus.test',
        'phone' => '+20 100 555 0199',
        'country' => 'eg',
        'tax_id' => 'TX-1234',
        'billing_address_line1' => '12 Nile Street',
        'billing_city' => 'Cairo',
        'preferred_locale' => 'ar',
        'notes' => 'Prefers WhatsApp.',
        ...$overrides,
    ];
}

test('staff without customer permissions get 403', function () {
    $customer = Customer::factory()->create();
    $editor = User::factory()->role(Role::ContentManager)->create();

    $this->actingAs($editor)->get(route('admin.customers.index'))->assertForbidden();
    $this->actingAs($editor)->get(route('admin.customers.show', $customer))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.customers.store'), customerPayload())->assertForbidden();
});

test('finance can view customers but not change them', function () {
    $customer = Customer::factory()->create(['company_name' => 'Visible Customer']);
    $finance = User::factory()->role(Role::FinanceManager)->withConfirmedTwoFactor()->create();

    $this->actingAs($finance)->get(route('admin.customers.index'))->assertSuccessful()->assertSee('Visible Customer')->assertDontSee(route('admin.customers.create'));
    $this->actingAs($finance)->get(route('admin.customers.show', $customer))->assertSuccessful()->assertDontSee(__('Invite a portal user'));
    $this->actingAs($finance)->get(route('admin.customers.create'))->assertForbidden();
    $this->actingAs($finance)->put(route('admin.customers.update', $customer), customerPayload())->assertForbidden();
    $this->actingAs($finance)->post(route('admin.customers.portal-users.store', $customer), ['name' => 'X', 'email' => 'x@x.test', 'locale' => 'en', 'role' => 'member'])->assertForbidden();
});

test('a sales manager creates, edits and deletes a customer with audit entries', function () {
    $this->actingAs($this->sales)->get(route('admin.customers.create'))->assertSuccessful();

    $this->actingAs($this->sales)->post(route('admin.customers.store'), customerPayload())->assertSessionHasNoErrors();

    $customer = Customer::query()->sole();

    expect($customer->email)->toBe('sara@clinicplus.test')
        ->and($customer->country)->toBe('EG')
        ->and($customer->reference)->toStartWith('CU-')
        ->and($customer->created_by)->toBe($this->sales->id)
        ->and(AuditLog::query()->where('operation', 'customer.created')->where('auditable_id', $customer->id)->exists())->toBeTrue();

    $this->actingAs($this->sales)->get(route('admin.customers.show', $customer))->assertSuccessful()->assertSee('ClinicPlus')->assertSee('12 Nile Street');
    $this->actingAs($this->sales)->get(route('admin.customers.index', ['q' => 'Clinic']))->assertSuccessful()->assertSee('ClinicPlus');

    $this->actingAs($this->sales)
        ->put(route('admin.customers.update', $customer), customerPayload(['company_name' => 'ClinicPlus LLC', 'status' => 'inactive']))
        ->assertRedirect(route('admin.customers.show', $customer));

    expect($customer->fresh()->company_name)->toBe('ClinicPlus LLC')
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Inactive)
        ->and(AuditLog::query()->where('operation', 'customer.updated')->exists())->toBeTrue();

    $this->actingAs($this->sales)->delete(route('admin.customers.destroy', $customer))->assertRedirect(route('admin.customers.index'));

    expect($customer->fresh()->trashed())->toBeTrue();
});

test('customer details are validated', function (array $overrides, string $field) {
    $this->actingAs($this->sales)->post(route('admin.customers.store'), customerPayload($overrides))->assertSessionHasErrors($field);
})->with([
    'missing name' => [['company_name' => ''], 'company_name'],
    'unknown type' => [['type' => 'robot'], 'type'],
    'bad country' => [['country' => 'XX'], 'country'],
    'inactive language' => [['preferred_locale' => 'fr'], 'preferred_locale'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
]);

test('a customer with active portal users cannot be deleted', function () {
    $customer = Customer::factory()->withPortalUser()->create();

    $this->actingAs($this->sales)->delete(route('admin.customers.destroy', $customer))->assertRedirect();

    expect($customer->fresh()->trashed())->toBeFalse();
});

test('a won lead is converted into a customer with prefilled details and a timeline entry', function () {
    $lead = Lead::factory()->status(LeadStatus::Won)->create(['name' => 'Omar Said', 'company' => 'Sample Foods', 'email' => 'omar@foods.test', 'preferred_locale' => 'ar']);

    $this->actingAs($this->sales)->get(route('admin.leads.show', $lead))->assertSee(route('admin.customers.create', ['lead' => $lead->ulid]), false);

    $this->actingAs($this->sales)->get(route('admin.customers.create', ['lead' => $lead->ulid]))
        ->assertSuccessful()
        ->assertSee('Sample Foods')
        ->assertSee('omar@foods.test')
        ->assertSee($lead->reference);

    $this->actingAs($this->sales)
        ->post(route('admin.customers.store'), customerPayload(['company_name' => 'Sample Foods', 'email' => 'omar@foods.test', 'lead' => $lead->ulid]))
        ->assertSessionHasNoErrors();

    $customer = Customer::query()->sole();

    expect($customer->created_from_lead_id)->toBe($lead->id)
        ->and($lead->activities()->where('type', LeadActivityType::System)->get()->contains(fn ($activity) => ($activity->meta['event'] ?? null) === 'converted'))->toBeTrue()
        ->and(AuditLog::query()->where('operation', 'customer.created_from_lead')->where('auditable_id', $customer->id)->exists())->toBeTrue();

    $this->actingAs($this->sales)->get(route('admin.leads.show', $lead))->assertSee(route('admin.customers.show', $customer), false);

    // A second conversion is refused.
    $this->actingAs($this->sales)
        ->post(route('admin.customers.store'), customerPayload(['lead' => $lead->ulid]))
        ->assertSessionHasErrors('lead');
    $this->actingAs($this->sales)->get(route('admin.customers.create', ['lead' => $lead->ulid]))->assertRedirect(route('admin.customers.show', $customer));

    expect(Customer::query()->count())->toBe(1);
});

test('only won leads can be converted', function () {
    $lead = Lead::factory()->status(LeadStatus::Negotiation)->create();

    $this->actingAs($this->sales)->post(route('admin.customers.store'), customerPayload(['lead' => $lead->ulid]))->assertSessionHasErrors('lead');
    $this->actingAs($this->sales)->get(route('admin.customers.create', ['lead' => $lead->ulid]))->assertRedirect(route('admin.leads.show', $lead));

    expect(Customer::query()->count())->toBe(0);
});

test('inviting a portal user creates a client login linked to the customer and sends a localized email', function () {
    $customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);

    $this->actingAs($this->sales)
        ->post(route('admin.customers.portal-users.store', $customer), ['name' => 'Sara Ahmed', 'email' => 'Sara@ClinicPlus.test', 'locale' => 'ar', 'role' => 'owner'])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'sara@clinicplus.test')->sole();
    $membership = $customer->users()->whereKey($user->id)->sole()->pivot;

    expect($user->hasRole(Role::Client->value))->toBeTrue()
        ->and($user->isStaff())->toBeFalse()
        ->and($membership->role)->toBe(CustomerUserRole::Owner)
        ->and(AuditLog::query()->where('operation', 'customer.portal_user_invited')->exists())->toBeTrue();

    Notification::assertSentTo($user, ClientInvitation::class, function (ClientInvitation $notification, array $channels, User $notifiable, ?string $locale) use ($user): bool {
        $mail = $notification->toMail($user);

        return $locale === 'ar' && str_contains($mail->actionUrl, '/reset-password/') && $notification->customerName === 'ClinicPlus';
    });

    // Rendered in the user's language (Arabic) through HasLocalePreference.
    app()->setLocale('ar');
    $mail = (new ClientInvitation('token', 'ClinicPlus'))->toMail($user);
    expect($mail->subject)->toBe(__('Your :app client portal access', ['app' => config('app.name')]))
        ->and($mail->subject)->not->toContain('client portal access');
});

test('portal invitations reject staff emails and duplicates', function () {
    $customer = Customer::factory()->create();
    $staff = User::factory()->role(Role::Developer)->create();
    $client = User::factory()->forCustomer($customer)->create();

    $this->actingAs($this->sales)
        ->post(route('admin.customers.portal-users.store', $customer), ['name' => 'Dev', 'email' => $staff->email, 'locale' => 'en', 'role' => 'member'])
        ->assertSessionHasErrorsIn('invitePortalUser', 'email');

    $this->actingAs($this->sales)
        ->post(route('admin.customers.portal-users.store', $customer), ['name' => 'Again', 'email' => $client->email, 'locale' => 'en', 'role' => 'member'])
        ->assertSessionHasErrorsIn('invitePortalUser', 'email');

    expect($staff->fresh()->hasRole(Role::Client->value))->toBeFalse();
});

test('an existing client can be linked to a second customer', function () {
    $first = Customer::factory()->create();
    $second = Customer::factory()->create();
    $client = User::factory()->forCustomer($first)->create();

    $this->actingAs($this->sales)
        ->post(route('admin.customers.portal-users.store', $second), ['name' => $client->name, 'email' => $client->email, 'locale' => 'en', 'role' => 'member'])
        ->assertSessionHasNoErrors();

    expect($client->customers()->count())->toBe(2)
        ->and(User::query()->where('email', $client->email)->count())->toBe(1);
});

test('the invitation can be resent and access switched off and restored', function () {
    $customer = Customer::factory()->create();
    $client = User::factory()->forCustomer($customer)->create();
    DB::table('sessions')->insert(['id' => 'client-session', 'user_id' => $client->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->sales)->post(route('admin.customers.portal-users.invitation', [$customer, $client]))->assertRedirect();
    Notification::assertSentTo($client, ClientInvitation::class);

    $this->actingAs($this->sales)->delete(route('admin.customers.portal-users.revoke', [$customer, $client]))->assertRedirect();

    expect($client->fresh()->is_active)->toBeFalse()
        ->and($customer->users()->whereKey($client->id)->sole()->pivot->revoked_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $client->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('operation', 'customer.portal_access_revoked')->exists())->toBeTrue();

    $this->actingAs($this->sales)->post(route('admin.customers.portal-users.restore', [$customer, $client]))->assertRedirect();

    expect($client->fresh()->is_active)->toBeTrue()
        ->and($customer->users()->whereKey($client->id)->sole()->pivot->revoked_at)->toBeNull();
});

test('portal user actions only work for users of that customer', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $foreignClient = User::factory()->forCustomer($other)->create();

    $this->actingAs($this->sales)->delete(route('admin.customers.portal-users.revoke', [$customer, $foreignClient]))->assertNotFound();
    $this->actingAs($this->sales)->post(route('admin.customers.portal-users.invitation', [$customer, $foreignClient]))->assertNotFound();

    expect($foreignClient->fresh()->is_active)->toBeTrue();
});

test('an invited client sets a password through the invitation link and lands on the portal', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->sales)
        ->post(route('admin.customers.portal-users.store', $customer), ['name' => 'Sara Ahmed', 'email' => 'sara@clinicplus.test', 'locale' => 'en', 'role' => 'owner']);
    auth()->logout();

    $user = User::query()->where('email', 'sara@clinicplus.test')->sole();
    $token = null;
    Notification::assertSentTo($user, ClientInvitation::class, function (ClientInvitation $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertSuccessful();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Portal-Password-2026!',
        'password_confirmation' => 'Portal-Password-2026!',
    ])->assertSessionHasNoErrors();

    expect(Password::broker()->tokenExists($user->fresh(), $token))->toBeFalse();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'Portal-Password-2026!'])->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('portal.dashboard'));
    $this->get(route('portal.dashboard'))->assertSuccessful()->assertSee($customer->company_name);
});

test('the permission matrix lists the customer permissions', function () {
    $superAdmin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();

    $this->actingAs($superAdmin)->get(route('admin.roles.index'))
        ->assertSuccessful()
        ->assertSee('customers.view')
        ->assertSee('customers.manage');

    expect(User::factory()->role(Role::ProjectManager)->create()->can('customers.manage'))->toBeTrue()
        ->and(User::factory()->role(Role::FinanceManager)->create()->can('customers.manage'))->toBeFalse()
        ->and(User::factory()->role(Role::Administrator)->create()->can('customers.manage'))->toBeTrue();
});
