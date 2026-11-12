<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Seed the supported languages (English default, Arabic RTL).
     */
    public function run(): void
    {
        foreach (config('codefanz.languages') as $index => $language) {
            Language::updateOrCreate(['code' => $language['code']], [...$language, 'is_active' => true, 'sort_order' => $index]);
        }
    }
}
