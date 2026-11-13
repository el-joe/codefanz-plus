<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Seed the supported languages (English default, Arabic RTL). Safe to re-run in production: missing languages are
     * created; existing rows keep their active flag, default flag and order, only the names and direction follow config.
     */
    public function run(): void
    {
        foreach (config('codefanz.languages') as $index => $language) {
            $model = Language::query()->firstOrCreate(['code' => $language['code']], [...$language, 'is_active' => true, 'sort_order' => $index]);

            if (! $model->wasRecentlyCreated) {
                $model->fill(collect($language)->only(['name', 'native_name', 'direction'])->all())->save();
            }
        }
    }
}
