<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Business settings stored in the database. Secrets are encrypted at rest and never cached in plain text.
 */
class Settings
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        // Cache the stored (possibly encrypted) row as a plain array; the cache store does not unserialize objects.
        $setting = Cache::remember("settings.{$group}.{$key}", now()->addHour(), fn (): ?array => Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first(['value', 'is_encrypted'])
            ?->only(['value', 'is_encrypted']));

        if ($setting === null || $setting['value'] === null) {
            return $default;
        }

        $raw = $setting['is_encrypted'] ? Crypt::decryptString($setting['value']) : $setting['value'];

        return json_decode($raw, true);
    }

    public function set(string $group, string $key, mixed $value, bool $encrypted = false, ?User $user = null): void
    {
        $payload = json_encode($value, JSON_THROW_ON_ERROR);

        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $encrypted ? Crypt::encryptString($payload) : $payload,
                'is_encrypted' => $encrypted,
                'updated_by' => $user?->getKey(),
            ],
        );

        Cache::forget("settings.{$group}.{$key}");
    }

    /**
     * Masked representation of a secret for display, e.g. "••••4f2a".
     */
    public function masked(string $group, string $key): ?string
    {
        $value = $this->get($group, $key);

        return is_string($value) && $value !== '' ? '••••'.mb_substr($value, -4) : null;
    }
}
