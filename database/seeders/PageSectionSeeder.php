<?php

namespace Database\Seeders;

use App\Models\PageSection;
use Illuminate\Database\Seeder;

/**
 * Structural homepage section rows (type + default order, no copy). Safe to re-run.
 * Sample copy lives in DemoContentSeeder.
 */
class PageSectionSeeder extends Seeder
{
    public function run(): void
    {
        PageSection::ensureHomeSections();
    }
}
