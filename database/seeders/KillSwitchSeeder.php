<?php

namespace Database\Seeders;

use App\Enums\KillSwitchKey;
use App\Models\KillSwitch;
use Illuminate\Database\Seeder;

class KillSwitchSeeder extends Seeder
{
    /**
     * Ensure every kill switch exists (inactive by default).
     */
    public function run(): void
    {
        foreach (KillSwitchKey::cases() as $key) {
            KillSwitch::firstOrCreate(['key' => $key], ['is_active' => false]);
        }
    }
}
