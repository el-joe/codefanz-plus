<?php

use App\Enums\Role;
use App\Models\User;

test('an active user can sign in and is sent to the dashboard redirect', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('a deactivated user cannot sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('a client cannot open the admin area', function () {
    $client = User::factory()->role(Role::Client)->create();

    $this->actingAs($client)->get(route('admin.dashboard'))->assertForbidden();
});

test('privileged staff must confirm two-factor authentication before using the admin area', function () {
    $admin = User::factory()->role(Role::Administrator)->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.security'));
    $this->actingAs($admin)->get(route('admin.security'))->assertRedirect(route('password.confirm'));
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.security'))->assertSuccessful();
});

test('privileged staff with confirmed two-factor authentication reach the admin dashboard', function () {
    $admin = User::factory()->withConfirmedTwoFactor()->role(Role::Administrator)->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSuccessful();
});

test('staff without a two-factor requirement reach the dashboard through the role-based redirect', function () {
    $editor = User::factory()->role(Role::SalesManager)->create();

    $this->actingAs($editor)->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($editor)->get(route('admin.dashboard'))->assertSuccessful();
});

test('a deactivated user is signed out on the next request', function () {
    $editor = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();
    $editor->forceFill(['is_active' => false])->save();

    $this->actingAs($editor)->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
