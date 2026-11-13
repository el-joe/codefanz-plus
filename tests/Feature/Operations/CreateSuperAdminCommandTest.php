<?php

use App\Enums\ActorType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an active, verified Super Admin from the prompts', function () {
    $this->artisan('app:create-super-admin', ['--name' => 'Owner Name', '--email' => 'Owner@CodeFanz.example'])
        ->expectsQuestion('Password (min. 12 characters, not found in known breaches)', 'a-strong-owner-passphrase')
        ->expectsQuestion('Repeat the password', 'a-strong-owner-passphrase')
        ->expectsOutputToContain('Super Admin owner@codefanz.example created')
        ->assertSuccessful();

    $user = User::query()->where('email', 'owner@codefanz.example')->firstOrFail();

    expect($user->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and(Hash::check('a-strong-owner-passphrase', $user->password))->toBeTrue();

    $audit = AuditLog::query()->where('operation', 'user.super_admin_created')->sole();
    expect($audit->actor_type)->toBe(ActorType::System)
        ->and($audit->auditable_id)->toBe($user->id)
        ->and(json_encode($audit->after))->not->toContain('a-strong-owner-passphrase');
});

test('name and e-mail are asked for when not given as options', function () {
    $this->artisan('app:create-super-admin', ['--locale' => 'ar'])
        ->expectsQuestion('Full name', 'مالك المنصة')
        ->expectsQuestion('E-mail address', 'owner@codefanz.example')
        ->expectsQuestion('Password (min. 12 characters, not found in known breaches)', 'a-strong-owner-passphrase')
        ->expectsQuestion('Repeat the password', 'a-strong-owner-passphrase')
        ->assertSuccessful();

    expect(User::query()->where('email', 'owner@codefanz.example')->value('locale'))->toBe('ar');
});

test('the new Super Admin must set up 2FA before reaching the admin area', function () {
    $this->artisan('app:create-super-admin', ['--name' => 'Owner', '--email' => 'owner@codefanz.example'])
        ->expectsQuestion('Password (min. 12 characters, not found in known breaches)', 'a-strong-owner-passphrase')
        ->expectsQuestion('Repeat the password', 'a-strong-owner-passphrase')
        ->assertSuccessful();

    $this->actingAs(User::query()->where('email', 'owner@codefanz.example')->firstOrFail())
        ->get('/admin')
        ->assertRedirect();
});

test('invalid input creates nothing', function (array $options, string $password, string $confirmation, string $error) {
    User::factory()->create(['email' => 'taken@codefanz.example']);

    $this->artisan('app:create-super-admin', ['--name' => 'Owner', ...$options])
        ->expectsQuestion('Password (min. 12 characters, not found in known breaches)', $password)
        ->expectsQuestion('Repeat the password', $confirmation)
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect(User::role(Role::SuperAdmin->value)->count())->toBe(0);
})->with([
    'short password' => [['--email' => 'owner@codefanz.example'], 'short', 'short', 'at least 12 characters'],
    'mismatch' => [['--email' => 'owner@codefanz.example'], 'a-strong-owner-passphrase', 'a-different-passphrase', 'confirmation does not match'],
    'taken e-mail' => [['--email' => 'taken@codefanz.example'], 'a-strong-owner-passphrase', 'a-strong-owner-passphrase', 'already been taken'],
    'bad e-mail' => [['--email' => 'not-an-email'], 'a-strong-owner-passphrase', 'a-strong-owner-passphrase', 'valid email'],
    'bad locale' => [['--email' => 'owner@codefanz.example', '--locale' => 'fr'], 'a-strong-owner-passphrase', 'a-strong-owner-passphrase', 'locale is invalid'],
]);

test('it refuses to run without the interactive password prompt', function () {
    $this->artisan('app:create-super-admin', ['--name' => 'Owner', '--email' => 'owner@codefanz.example', '--no-interaction' => true])
        ->expectsOutputToContain('interactive prompt')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
