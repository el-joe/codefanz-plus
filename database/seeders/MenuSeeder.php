<?php

namespace Database\Seeders;

use App\Enums\MenuLocation;
use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * One (empty) menu per layout location. Items are added in Admin → Menus (sample items in DemoContentSeeder).
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MenuLocation::cases() as $location) {
            Menu::for($location);
        }
    }
}
