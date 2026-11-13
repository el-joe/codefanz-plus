<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed essential system data only. Demo content is never seeded here.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            RolesAndPermissionsSeeder::class,
            KillSwitchSeeder::class,
            AiStudioSeeder::class,
            RadarPlatformSeeder::class,
            PageSectionSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
