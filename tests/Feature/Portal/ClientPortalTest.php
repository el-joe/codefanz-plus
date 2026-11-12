<?php

use App\Enums\CustomerUserRole;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Services\CurrentCustomer;

beforeEach(function () {
    $this->customer = Customer::factory()->create(['company_name' => 'ClinicPlus']);
    $this->client = User::factory()->forCustomer($this->customer, CustomerUserRole::Owner)->create(['name' => 'Sara Ahmed', 'locale' => 'en']);
});

test('a client signs in and lands on the portal', function () {
    $this->post(route('login'), ['email' => $this->client->email, 'password' => 'password'])->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertRedirect(route('portal.dashboard'));
    $this->get(route('portal.dashboard'))
        ->assertSuccessful()
        ->assertSee('ClinicPlus')
        ->assertSee(__('No projects yet'))
        ->assertSee('data-nav="projects"', false)
        ->assertSee('data-tab="payments"', false);
});

test('a client cannot open the admin area', function () {
    $this->actingAs($this->client)->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs($this->client)->get(route('admin.customers.index'))->assertForbidden();
});

test('staff are sent from the portal to the admin', function () {
    $staff = User::factory()->role(Role::ContentManager)->create();

    $this->actingAs($staff)->get(route('portal.dashboard'))->assertRedirect(route('admin.dashboard'));
    $this->actingAs($staff)->get(route('portal.profile'))->assertRedirect(route('admin.dashboard'));
});

test('guests are sent to the login page', function () {
    $this->get(route('portal.dashboard'))->assertRedirect(route('login'));
});

test('a client without an active customer membership is blocked', function (Closure $setup) {
    $setup($this);

    $this->actingAs($this->client)->get(route('portal.dashboard'))->assertForbidden();
    $this->actingAs($this->client)->get(route('portal.projects.index'))->assertForbidden();
})->with([
    'not linked to any customer' => [fn ($test) => $test->client = User::factory()->client()->create()],
    'customer inactive' => [fn ($test) => $test->customer->update(['status' => 'inactive'])],
    'membership revoked' => [fn ($test) => $test->customer->users()->updateExistingPivot($test->client->id, ['revoked_at' => now()])],
    'customer deleted' => [fn ($test) => $test->customer->delete()],
]);

test('a deactivated client is signed out on the next request', function () {
    $this->client->forceFill(['is_active' => false])->save();

    $this->actingAs($this->client)->get(route('portal.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('every portal screen renders in English and Arabic with the right direction', function (string $routeName) {
    foreach (['en' => 'ltr', 'ar' => 'rtl'] as $locale => $direction) {
        $this->client->forceFill(['locale' => $locale])->save();

        $this->actingAs($this->client)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route($routeName))
            ->assertSuccessful()
            ->assertSee('<html lang="'.$locale.'" dir="'.$direction.'">', false)
            ->assertSee($locale === 'ar' ? 'لوحة التحكم' : 'Dashboard');
    }
})->with([
    'portal.dashboard', 'portal.projects.index', 'portal.invoices.index', 'portal.payments.index', 'portal.documents.index',
    'portal.conversations.index', 'portal.support.index', 'portal.notifications.index', 'portal.profile', 'portal.security',
]);

test('the security page asks for the password first and two-factor stays optional', function () {
    $this->actingAs($this->client)->get(route('portal.security'))->assertRedirect(route('password.confirm'));

    $this->actingAs($this->client)->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('portal.security'))
        ->assertSuccessful()
        ->assertSee(__('Set up two-factor authentication'));

    expect($this->client->requiresTwoFactor())->toBeFalse();
});

test('a client updates their profile from the portal', function () {
    $this->actingAs($this->client)
        ->from(route('portal.profile'))
        ->put(route('user-profile-information.update'), ['name' => 'Sara Nabil', 'email' => 'sara@clinicplus.test', 'locale' => 'ar', 'timezone' => 'Africa/Cairo'])
        ->assertRedirect(route('portal.profile'));

    expect($this->client->fresh()->locale)->toBe('ar');

    $this->actingAs($this->client->fresh())->get(route('portal.profile'))->assertSuccessful()->assertSee('dir="rtl"', false)->assertSee('Sara Nabil');
});

test('portal notifications only show the signed-in user’s own notifications', function () {
    $other = User::factory()->forCustomer(Customer::factory()->create())->create();
    $mine = $this->client->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'test', 'data' => ['kind' => 'test']]);
    $theirs = $other->notifications()->create(['id' => (string) str()->uuid(), 'type' => 'test', 'data' => ['kind' => 'test']]);

    $this->actingAs($this->client)->get(route('portal.notifications.index'))->assertSuccessful()->assertSee(route('portal.notifications.read', $mine->id), false)->assertDontSee($theirs->id);
    $this->actingAs($this->client)->post(route('portal.notifications.read', $theirs->id))->assertNotFound();
    $this->actingAs($this->client)->post(route('portal.notifications.read', $mine->id))->assertRedirect(route('portal.notifications.index'));

    expect($mine->fresh()->read_at)->not->toBeNull()
        ->and($theirs->fresh()->read_at)->toBeNull();
});

test('a client in several customers can switch between them', function () {
    $second = Customer::factory()->create(['company_name' => 'Second Account']);
    $second->users()->attach($this->client, ['role' => CustomerUserRole::Member, 'invited_at' => now()]);

    $this->actingAs($this->client)->get(route('portal.dashboard'))->assertSee('Second Account')->assertSee(route('portal.account.switch'), false);

    $this->actingAs($this->client)->post(route('portal.account.switch'), ['customer' => $second->ulid])->assertRedirect(route('portal.dashboard'));

    expect(session(CurrentCustomer::SESSION_KEY))->toBe($second->ulid)
        ->and(AuditLog::query()->where('operation', 'portal.account_switched')->where('auditable_id', $second->id)->exists())->toBeTrue();

    $this->actingAs($this->client)->get(route('portal.profile'))->assertSee($second->reference);
});

test('the account switcher rejects customers the client does not belong to', function () {
    $foreign = Customer::factory()->withPortalUser()->create(['company_name' => 'Foreign Co']);
    $revoked = Customer::factory()->create();
    $revoked->users()->attach($this->client, ['role' => CustomerUserRole::Member, 'revoked_at' => now()]);

    foreach ([$foreign->ulid, $revoked->ulid, '01JZZZZZZZZZZZZZZZZZZZZZZZ'] as $ulid) {
        $this->actingAs($this->client)->withSession([CurrentCustomer::SESSION_KEY => $this->customer->ulid])
            ->post(route('portal.account.switch'), ['customer' => $ulid])
            ->assertNotFound();

        expect(session(CurrentCustomer::SESSION_KEY))->toBe($this->customer->ulid);
    }

    $this->actingAs($this->client)->get(route('portal.dashboard'))->assertDontSee('Foreign Co');
});

test('a forged session customer falls back to the client’s own customer', function () {
    $foreign = Customer::factory()->withPortalUser()->create(['company_name' => 'Foreign Co']);

    $this->actingAs($this->client)->withSession([CurrentCustomer::SESSION_KEY => $foreign->ulid])
        ->get(route('portal.profile'))
        ->assertSuccessful()
        ->assertSee($this->customer->reference)
        ->assertDontSee('Foreign Co');

    expect(session(CurrentCustomer::SESSION_KEY))->toBe($this->customer->ulid);
});

test('a client loses the customer if access is revoked mid-session', function () {
    $second = Customer::factory()->create();
    $second->users()->attach($this->client, ['role' => CustomerUserRole::Member, 'invited_at' => now()]);

    $this->actingAs($this->client)->post(route('portal.account.switch'), ['customer' => $second->ulid]);
    $second->users()->updateExistingPivot($this->client->id, ['revoked_at' => now()]);

    $this->actingAs($this->client)->get(route('portal.profile'))->assertSuccessful()->assertSee($this->customer->reference)->assertDontSee($second->reference);
});
