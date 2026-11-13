<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Fortify\RecoveryCode;
use RuntimeException;

/**
 * Local-only QA accounts (never called by DatabaseSeeder, refuses to run outside APP_ENV=local):
 * a Super Admin and a Content Manager (both with confirmed 2FA) and a known password for the demo portal client.
 * Passwords and the TOTP secret come from DEV_* variables in the developer's own .env file.
 */
class LocalDevAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalDevAccountsSeeder only runs when APP_ENV=local.');
        }

        $config = config('codefanz.local_dev_accounts');

        foreach (['admin_password' => 'DEV_ADMIN_PASSWORD', 'admin_two_factor_secret' => 'DEV_ADMIN_2FA_SECRET', 'editor_password' => 'DEV_EDITOR_PASSWORD', 'client_password' => 'DEV_CLIENT_PASSWORD'] as $key => $variable) {
            if (blank($config[$key] ?? null)) {
                throw new RuntimeException("Set {$variable} in .env before running LocalDevAccountsSeeder.");
            }
        }

        // Both hold approval permissions, which require confirmed 2FA (M11.1): they share the local TOTP secret.
        foreach ([
            ['admin@codefanz.test', 'Local Super Admin', $config['admin_password'], Role::SuperAdmin],
            ['editor@codefanz.test', 'Local Content Manager', $config['editor_password'], Role::ContentManager],
        ] as [$email, $name, $password, $role]) {
            $this->staff($email, $name, $password, $role)->forceFill([
                'two_factor_secret' => encrypt($config['admin_two_factor_secret']),
                'two_factor_recovery_codes' => encrypt(json_encode(collect(range(1, 8))->map(fn (): string => RecoveryCode::generate())->all())),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        $client = User::query()->where('email', 'client.demo@example.test')->first();

        if ($client === null) {
            $this->command?->warn('client.demo@example.test not found: run DemoContentSeeder first to create the demo customer.');

            return;
        }

        $client->forceFill(['password' => $config['client_password'], 'is_active' => true, 'email_verified_at' => $client->email_verified_at ?? now()])->save();
        $client->syncRoles([Role::Client->value]);
    }

    private function staff(string $email, string $name, string $password, Role $role): User
    {
        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'locale' => 'en',
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'deleted_at' => null,
        ])->save();
        $user->syncRoles([$role->value]);

        return $user;
    }
}
