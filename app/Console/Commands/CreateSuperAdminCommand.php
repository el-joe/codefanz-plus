<?php

namespace App\Console\Commands;

use App\Enums\ActorType;
use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * First-run bootstrap of the platform owner. The password is read from a hidden prompt only (never an option,
 * argument or environment variable, so it cannot leak into shell history or process lists). Two-factor
 * authentication is enforced at the first sign-in by the admin 2FA gate.
 */
#[Signature('app:create-super-admin {--name= : Full name} {--email= : Sign-in e-mail address} {--locale=en : Interface and e-mail language (en or ar)}')]
#[Description('Create a Super Admin account (password entered at a hidden prompt)')]
class CreateSuperAdminCommand extends Command
{
    public function handle(AuditLogger $audit): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('The password can only be entered at the interactive prompt. Run the command without --no-interaction.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Full name')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail address'))));
        $locale = (string) $this->option('locale');
        $password = (string) $this->secret('Password (min. 12 characters, not found in known breaches)');
        $confirmation = (string) $this->secret('Repeat the password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'locale' => $locale, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'locale' => ['required', Rule::in(['en', 'ar'])],
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $locale, $password, $audit): User {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password, 'locale' => $locale, 'is_active' => true]);
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole(Role::SuperAdmin->value);

            $audit->record(
                operation: 'user.super_admin_created',
                auditable: $user,
                actorType: ActorType::System,
                actorLabel: 'artisan app:create-super-admin',
                after: ['name' => $name, 'email' => $email, 'roles' => [Role::SuperAdmin->value]],
            );

            return $user;
        });

        $this->info("Super Admin {$user->email} created. Sign in at ".url('/login').' — you will be asked to set up two-factor authentication first.');

        return self::SUCCESS;
    }
}
