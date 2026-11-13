<?php

namespace App\Services;

use App\Enums\KillSwitchKey;
use App\Models\KillSwitch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class KillSwitches
{
    private const CACHE_KEY = 'kill-switches';

    public function __construct(private AuditLogger $audit) {}

    public function isActive(KillSwitchKey $key): bool
    {
        return (bool) ($this->states()[$key->value] ?? false);
    }

    public function set(KillSwitchKey $key, bool $active, User $user, ?string $reason = null): KillSwitch
    {
        $switch = KillSwitch::firstOrNew(['key' => $key]);
        $before = ['is_active' => (bool) $switch->is_active];

        $switch->fill(['is_active' => $active, 'reason' => $reason, 'changed_by' => $user->getKey()])->save();
        Cache::memo()->forget(self::CACHE_KEY);

        $this->audit->record(
            operation: 'kill_switch.'.($active ? 'activated' : 'deactivated'),
            auditable: $switch,
            user: $user,
            before: $before,
            after: ['is_active' => $active, 'reason' => $reason],
        );

        return $switch;
    }

    /**
     * @return array<string, bool>
     */
    private function states(): array
    {
        return Cache::memo()->remember(self::CACHE_KEY, now()->addMinutes(5), fn (): array => KillSwitch::query()
            ->pluck('is_active', 'key')
            ->map(fn ($value): bool => (bool) $value)
            ->all());
    }
}
