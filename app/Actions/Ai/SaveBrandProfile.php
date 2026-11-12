<?php

namespace App\Actions\Ai;

use App\Models\BrandProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the brand voice used by every Content Studio prompt (optimistic locking, audited).
 */
class SaveBrandProfile
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(BrandProfile $profile, array $data, User $user): BrandProfile
    {
        return DB::transaction(function () use ($profile, $data, $user): BrandProfile {
            $profile = BrandProfile::query()->lockForUpdate()->findOrFail($profile->getKey());

            if ((int) ($data['lock_version'] ?? 0) !== $profile->lock_version) {
                throw ValidationException::withMessages(['lock_version' => __('Someone else changed the brand voice while you were editing. Reload to see their changes.')]);
            }

            $before = $profile->only(['name', 'tone', 'audience', 'do_rules', 'dont_rules', 'banned_claims', 'glossary', 'allowed_domains']);

            $profile->fill([
                'name' => $data['name'],
                'tone' => $data['tone'] ?? [],
                'audience' => $data['audience'] ?? [],
                'do_rules' => $data['do_rules'] ?? [],
                'dont_rules' => $data['dont_rules'] ?? [],
                'banned_claims' => $data['banned_claims'] ?? [],
                'glossary' => $data['glossary'] ?? [],
                'allowed_domains' => $data['allowed_domains'] ?? [],
                'updated_by' => $user->getKey(),
            ]);
            $profile->lock_version++;
            $profile->save();

            $this->audit->record(operation: 'brand_profile.updated', auditable: $profile, user: $user, before: $before, after: $profile->only(array_keys($before)));

            return $profile;
        });
    }
}
