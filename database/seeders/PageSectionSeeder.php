<?php

namespace Database\Seeders;

use App\Models\PageSection;
use Illuminate\Database\Seeder;

/**
 * Structural section rows for every section-managed page (type + default order, no copy). Safe to re-run.
 * Sample copy lives in DemoContentSeeder.
 */
class PageSectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys(PageSection::PAGES) as $page) {
            PageSection::ensureSections($page);
        }
    }
}
