<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalDevAccountsSeeder;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

function configureLocalDevAccounts(): void
{
    config(['codefanz.local_dev_accounts' => [
        'admin_password' => 'local-admin-password-123',
        'admin_two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        'editor_password' => 'local-editor-password-123',
        'client_password' => 'local-client-password-123',
    ]]);
}

test('the local accounts seeder refuses to run outside the local environment', function (string $environment) {
    $this->app->detectEnvironment(fn () => $environment);
    configureLocalDevAccounts();

    expect(fn () => app(LocalDevAccountsSeeder::class)->run())->toThrow(RuntimeException::class, 'APP_ENV=local');
    expect(User::query()->where('email', 'admin@codefanz.test')->exists())->toBeFalse();
})->with(['testing', 'staging', 'production']);

test('the local accounts seeder needs every credential from the environment', function () {
    $this->app->detectEnvironment(fn () => 'local');
    config(['codefanz.local_dev_accounts.admin_password' => null]);

    expect(fn () => app(LocalDevAccountsSeeder::class)->run())->toThrow(RuntimeException::class, 'DEV_ADMIN_PASSWORD');
});

test('locally it creates a super admin and a content manager with confirmed 2FA and sets the demo client password', function () {
    $this->app->detectEnvironment(fn () => 'local');
    configureLocalDevAccounts();
    $client = User::factory()->client()->create(['email' => 'client.demo@example.test']);

    app(LocalDevAccountsSeeder::class)->run();
    app(LocalDevAccountsSeeder::class)->run();
    $this->app->detectEnvironment(fn () => 'testing');

    $admin = User::query()->where('email', 'admin@codefanz.test')->sole();
    $editor = User::query()->where('email', 'editor@codefanz.test')->sole();

    expect($admin->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and(Hash::check('local-admin-password-123', $admin->password))->toBeTrue()
        ->and($admin->two_factor_confirmed_at)->not->toBeNull()
        ->and(decrypt($admin->two_factor_secret))->toBe('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')
        ->and($editor->hasRole(Role::ContentManager->value))->toBeTrue()
        ->and($editor->two_factor_confirmed_at)->not->toBeNull()
        ->and(Hash::check('local-client-password-123', $client->fresh()->password))->toBeTrue()
        ->and($client->fresh()->hasRole(Role::Client->value))->toBeTrue();

    // The stored secret drives the real 2FA challenge.
    $this->post('/login', ['email' => 'admin@codefanz.test', 'password' => 'local-admin-password-123'])->assertRedirect('/two-factor-challenge');
    $this->post('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')])->assertRedirect();
    $this->assertAuthenticatedAs($admin);
});

test('the database seeder never creates the local accounts', function () {
    expect(file_get_contents(database_path('seeders/DatabaseSeeder.php')))->not->toContain('LocalDevAccountsSeeder');
    expect(class_exists(DatabaseSeeder::class))->toBeTrue();
});
