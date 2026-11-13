<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create(['password' => 'Old-password-123']);
});

test('the profile page renders', function () {
    $this->actingAs($this->user)->get(route('admin.profile'))->assertSuccessful()->assertSee($this->user->email);
});

test('a user updates their own profile and language', function () {
    $this->actingAs($this->user)
        ->put(route('user-profile-information.update'), ['name' => 'Sara Nabil', 'email' => 'Sara@CodeFanz.test', 'locale' => 'ar', 'timezone' => 'Asia/Dubai'])
        ->assertSessionHasNoErrors();

    $user = $this->user->fresh();

    expect($user->name)->toBe('Sara Nabil')
        ->and($user->email)->toBe('sara@codefanz.test')
        ->and($user->locale)->toBe('ar')
        ->and($user->timezone)->toBe('Asia/Dubai')
        ->and(AuditLog::query()->where('operation', 'profile.updated')->exists())->toBeTrue();
});

test('profile errors are shown next to the right field', function () {
    User::factory()->create(['email' => 'taken@codefanz.test']);

    $this->actingAs($this->user)
        ->from(route('admin.profile'))
        ->put(route('user-profile-information.update'), ['name' => 'Sara', 'email' => 'taken@codefanz.test', 'locale' => 'en', 'timezone' => 'UTC'])
        ->assertSessionHasErrorsIn('updateProfileInformation', 'email');

    $this->actingAs($this->user)->followingRedirects()
        ->from(route('admin.profile'))
        ->put(route('user-profile-information.update'), ['name' => 'Sara', 'email' => 'taken@codefanz.test', 'locale' => 'en', 'timezone' => 'UTC'])
        ->assertSee('aria-invalid="true"', false);
});

test('changing the password signs out other sessions', function () {
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->user)
        ->put(route('user-password.update'), ['current_password' => 'Old-password-123', 'password' => 'New-password-456!', 'password_confirmation' => 'New-password-456!'])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('operation', 'password.updated')->exists())->toBeTrue();
});

test('the current password is required to change it', function () {
    $this->actingAs($this->user)
        ->put(route('user-password.update'), ['current_password' => 'wrong', 'password' => 'New-password-456!', 'password_confirmation' => 'New-password-456!'])
        ->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect(AuditLog::query()->where('operation', 'password.updated')->exists())->toBeFalse();
});
