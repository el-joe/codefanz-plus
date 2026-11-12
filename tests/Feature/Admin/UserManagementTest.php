<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->superAdmin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();
});

/**
 * @return array<string, mixed>
 */
function staffPayload(array $overrides = []): array
{
    return [
        'name' => 'Mona Adel',
        'email' => 'Mona@CodeFanz.test',
        'locale' => 'ar',
        'timezone' => 'Africa/Cairo',
        'roles' => [Role::ContentManager->value],
        'is_active' => '1',
        ...$overrides,
    ];
}

function addSession(User $user, string $id): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
}

test('only users with the users permission can manage staff', function () {
    $administrator = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();

    $this->actingAs($administrator)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($administrator)->post(route('admin.users.store'), staffPayload())->assertForbidden();
});

test('a super admin invites a staff member who receives a localized invitation', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.users.store'), staffPayload())
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'mona@codefanz.test')->sole();

    expect($user->hasRole(Role::ContentManager->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->preferredLocale())->toBe('ar')
        ->and(AuditLog::query()->where('operation', 'user.invited')->where('auditable_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, StaffInvitation::class, function (StaffInvitation $notification) use ($user): bool {
        return str_contains($notification->toMail($user)->actionUrl, '/reset-password/');
    });
});

test('staff details are validated', function (array $overrides, string $field) {
    User::factory()->create(['email' => 'taken@codefanz.test']);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.users.store'), staffPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'duplicate email' => [['email' => 'TAKEN@codefanz.test'], 'email'],
    'no roles' => [['roles' => []], 'roles'],
    'client role' => [['roles' => [Role::Client->value]], 'roles.0'],
    'unknown time zone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'inactive language' => [['locale' => 'fr'], 'locale'],
]);

test('the user list can be searched and filtered by role', function () {
    User::factory()->role(Role::SeoSpecialist)->create(['name' => 'Karim SEO']);
    User::factory()->role(Role::Developer)->create(['name' => 'Laila Dev']);
    User::factory()->role(Role::Client)->create(['name' => 'Client Person']);

    $this->actingAs($this->superAdmin)->get(route('admin.users.index'))
        ->assertSee('Karim SEO')->assertSee('Laila Dev')->assertDontSee('Client Person');

    $this->actingAs($this->superAdmin)->get(route('admin.users.index', ['role' => Role::Developer->value]))
        ->assertSee('Laila Dev')->assertDontSee('Karim SEO');

    $this->actingAs($this->superAdmin)->get(route('admin.users.index', ['q' => 'karim']))
        ->assertSee('Karim SEO')->assertDontSee('Laila Dev');
});

test('deactivating a user signs them out everywhere', function () {
    $user = User::factory()->role(Role::SupportAgent)->create();
    $user->createToken('cli');
    addSession($user, 'session-a');

    $this->actingAs($this->superAdmin)
        ->put(route('admin.users.update', $user), staffPayload(['email' => $user->email, 'roles' => [Role::SupportAgent->value], 'is_active' => '0']))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->is_active)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0);

    $audit = AuditLog::query()->where('operation', 'user.updated')->sole();
    expect($audit->before['is_active'])->toBeTrue()->and($audit->after['is_active'])->toBeFalse();
});

test('you cannot lock yourself out', function (array $overrides, string $field) {
    $this->actingAs($this->superAdmin)
        ->put(route('admin.users.update', $this->superAdmin), staffPayload(['email' => $this->superAdmin->email, 'roles' => [Role::SuperAdmin->value], ...$overrides]))
        ->assertSessionHasErrors($field);

    expect($this->superAdmin->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and($this->superAdmin->fresh()->is_active)->toBeTrue();
})->with([
    'deactivate self' => [['is_active' => '0'], 'is_active'],
    'drop own user management' => [['roles' => [Role::ContentManager->value]], 'roles'],
]);

test('only a super admin can grant or edit the super admin role', function () {
    $manager = User::factory()->role(Role::ContentManager)->create();
    $manager->givePermissionTo('users.manage');
    $colleague = User::factory()->role(Role::SupportAgent)->create();

    $this->actingAs($manager)
        ->put(route('admin.users.update', $colleague), staffPayload(['email' => $colleague->email, 'roles' => [Role::SuperAdmin->value]]))
        ->assertSessionHasErrors('roles');

    $this->actingAs($manager)->get(route('admin.users.edit', $this->superAdmin))->assertForbidden();

    expect($colleague->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse();
});

test('resetting a colleague two-factor requires password confirmation', function () {
    $colleague = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();

    $this->actingAs($this->superAdmin)
        ->delete(route('admin.users.two-factor.destroy', $colleague))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($this->superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('admin.users.two-factor.destroy', $colleague))
        ->assertSessionHasNoErrors();

    expect($colleague->fresh()->hasConfirmedTwoFactor())->toBeFalse()
        ->and($colleague->fresh()->two_factor_secret)->toBeNull()
        ->and(AuditLog::query()->where('operation', 'user.two_factor_reset')->exists())->toBeTrue();
});

test('you cannot reset your own two-factor from user management', function () {
    $this->actingAs($this->superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('admin.users.two-factor.destroy', $this->superAdmin))
        ->assertForbidden();
});

test('an invitation can be resent', function () {
    $user = User::factory()->role(Role::Developer)->create();

    $this->actingAs($this->superAdmin)->post(route('admin.users.invitation', $user))->assertSessionHasNoErrors();

    Notification::assertSentTo($user, StaffInvitation::class);
});

test('the edit screen and permission matrix render', function () {
    $user = User::factory()->role(Role::SeoSpecialist)->create();

    $this->actingAs($this->superAdmin)->get(route('admin.users.edit', $user))->assertSuccessful()->assertSee($user->email);
    $this->actingAs($this->superAdmin)->get(route('admin.users.create'))->assertSuccessful();
    $this->actingAs($this->superAdmin)->get(route('admin.roles.index'))->assertSuccessful()->assertSee('seo.approve');
});
