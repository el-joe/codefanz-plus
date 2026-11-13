<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;

/*
| M11 security review: password policy, throttling of Fortify's unthrottled credential routes, two-factor for
| every approver, and the admin overview no longer leaking the approval queue and audit trail.
*/

function resetPasswordWith(string $password): TestResponse
{
    $user = User::factory()->create(['email' => 'reset@example.test']);
    $token = Password::broker()->createToken($user);

    return test()->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

test('new passwords need at least 12 characters', function () {
    resetPasswordWith('Short-pass1')->assertSessionHasErrors('password');
    expect(User::query()->where('email', 'reset@example.test')->first()->password)->not->toBeNull();
});

test('a 12 character password is accepted', function () {
    resetPasswordWith('Twelve-char1')->assertSessionHasNoErrors()->assertRedirect(route('login'));
});

test('breached passwords are refused when the breach check is on', function () {
    config(['codefanz.security.check_breached_passwords' => true]);
    $hash = strtoupper(sha1('Correct-horse-1'));
    Http::fake(['api.pwnedpasswords.com/range/'.substr($hash, 0, 5) => Http::response(substr($hash, 5).":4021\r\n0018A45C4D1DEF81644B54AB7F969B88D65:1")]);

    resetPasswordWith('Correct-horse-1')->assertSessionHasErrors('password');
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.pwnedpasswords.com/range/'));
});

test('password reset link requests are rate limited per address', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => "nobody{$attempt}@example.test"])->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => 'nobody6@example.test'])->assertTooManyRequests();
});

test('password resets, confirmations and changes are rate limited', function (string $route, string $method) {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $status = $this->actingAs($user)->call($method, route($route), ['password' => 'wrong', 'current_password' => 'wrong', 'code' => '000000'])->status();
        expect($status)->not->toBe(429);
    }

    $this->actingAs($user)->call($method, route($route), ['password' => 'wrong'])->assertTooManyRequests();
})->with([
    'confirm password' => ['password.confirm.store', 'POST'],
    'change password' => ['user-password.update', 'PUT'],
    'confirm two-factor' => ['two-factor.confirm', 'POST'],
]);

test('staff who can approve changes must confirm two-factor even without a privileged role', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('admin.security'));

    $user->forceFill(['two_factor_confirmed_at' => now()])->save();
    $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertSuccessful();
})->with([Role::ContentManager, Role::SeoSpecialist, Role::SocialMediaManager]);

test('a directly granted approval permission also requires two-factor', function () {
    $user = User::factory()->role(Role::SalesManager)->create();
    $this->actingAs($user)->get(route('admin.dashboard'))->assertSuccessful();

    $user->givePermissionTo(Permission::SeoApprove->value);

    $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertRedirect(route('admin.security'));
});

test('the admin overview hides the approval queue and audit trail from staff without those permissions', function () {
    ChangeRequest::factory()->create(['summary' => 'Secret pricing page rewrite']);
    AuditLog::create(['operation' => 'payments.refund.approved', 'actor_type' => 'user', 'result' => 'success']);

    $sales = User::factory()->role(Role::SalesManager)->create();
    $this->actingAs($sales)->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertDontSee('Secret pricing page rewrite')
        ->assertDontSee('payments.refund.approved')
        ->assertDontSee(__('Approval queue'))
        ->assertDontSee(__('Recent activity'));

    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();
    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertSee('Secret pricing page rewrite')
        ->assertSee('payments.refund.approved');
});

test('the admin overview renders in arabic', function () {
    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create(['locale' => 'ar']);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSuccessful()->assertSee('dir="rtl"', false);
});

test('session cookies are http-only and same-site lax', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});
