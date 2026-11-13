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
        // A missing row is cached as a null value too: a null result would never be cached and every request would
        // query and re-write the cache. The memoized store answers repeated reads within one request.
        $setting = Cache::memo()->remember("settings.{$group}.{$key}", now()->addHour(), fn (): array => Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first(['value', 'is_encrypted'])
            ?->only(['value', 'is_encrypted']) ?? ['value' => null, 'is_encrypted' => false]);

        if (! is_array($setting) || $setting['value'] === null) {
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

        Cache::memo()->forget("settings.{$group}.{$key}");
    }

    public function forget(string $group, string $key): void
    {
        Setting::query()->where('group', $group)->where('key', $key)->delete();

        Cache::memo()->forget("settings.{$group}.{$key}");
    }

    public function has(string $group, string $key): bool
    {
        $value = $this->get($group, $key);

        return $value !== null && $value !== '' && $value !== [];
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
