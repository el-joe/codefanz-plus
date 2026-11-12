<?php

namespace Database\Factories;

use App\Models\BrandProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandProfile>
 */
class BrandProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'CodeFanz',
            'is_default' => true,
            'tone' => ['en' => 'Clear and practical.', 'ar' => 'واضح وعملي.'],
            'audience' => ['en' => 'Founders and CTOs.', 'ar' => 'المؤسسون والمديرون التقنيون.'],
            'do_rules' => ['en' => ['Explain trade-offs'], 'ar' => ['اشرح المفاضلات']],
            'dont_rules' => ['en' => ['Invent statistics'], 'ar' => ['اختلاق الإحصاءات']],
            'banned_claims' => ['guaranteed', '#1'],
            'glossary' => [['en' => 'customer portal', 'ar' => 'بوابة العملاء']],
            'allowed_domains' => ['laravel.com'],
        ];
    }
}
