<?php

namespace App\Actions\Fortify;

use App\Models\Language;
use App\Models\User;
use App\Services\AuditLogger;
use DateTimeZone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));

        Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'locale' => ['required', Rule::in(Language::active()->keys()->all())],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
        ])->validateWithBag('updateProfileInformation');

        $before = $user->only(['name', 'email', 'locale', 'timezone']);

        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'locale' => $input['locale'],
            'timezone' => $input['timezone'],
        ])->save();

        if ($user->wasChanged()) {
            $this->audit->record(operation: 'profile.updated', auditable: $user, user: $user, before: $before, after: $user->only(['name', 'email', 'locale', 'timezone']));
        }
    }
}
