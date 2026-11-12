<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\PricingModel;
use App\Enums\ProductAvailability;
use App\Enums\ProjectType;
use App\Enums\ServiceTier;
use App\Enums\TechnologyProficiency;
use App\Models\Industry;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\Product;
use App\Models\Service;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use Illuminate\Database\Seeder;

/**
 * Clearly-sample bilingual catalog content for local demos and design reviews.
 *
 * Never called by DatabaseSeeder. Run with: php artisan db:seed --class=DemoContentSeeder
 * Re-running is safe: records are matched by slug/key and skipped when present.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $technologies = $this->technologies();
        $this->services($technologies);
        $this->industries();
        $this->portfolio($technologies);
        $this->products($technologies);
    }

    /**
     * @return array<string, int>
     */
    private function technologies(): array
    {
        $catalog = [
            'languages' => [['en' => 'Programming languages', 'ar' => 'لغات البرمجة'], [
                'php' => ['PHP', TechnologyProficiency::Expertise],
                'typescript' => ['TypeScript', TechnologyProficiency::Expertise],
                'python' => ['Python', TechnologyProficiency::Supported],
            ]],
            'frameworks' => [['en' => 'Frameworks', 'ar' => 'أطر العمل'], [
                'laravel' => ['Laravel', TechnologyProficiency::Expertise],
                'flutter' => ['Flutter', TechnologyProficiency::Supported],
                'django' => ['Django', TechnologyProficiency::Supported],
            ]],
        ];

        $ids = [];

        foreach (array_keys($catalog) as $position => $categoryKey) {
            [$names, $items] = $catalog[$categoryKey];

            $category = TechnologyCategory::query()->firstOrCreate(['key' => $categoryKey], ['sort_order' => $position]);
            $category->syncTranslations(collect($names)->map(fn (string $name): array => ['name' => $name])->all(), ['name'], 'name');

            foreach (array_keys($items) as $order => $key) {
                [$name, $level] = $items[$key];

                $technology = Technology::query()->firstOrCreate(['key' => $key], [
                    'technology_category_id' => $category->id,
                    'proficiency' => $level,
                    'sort_order' => $order,
                ]);
                $technology->syncTranslations([
                    'en' => ['name' => $name, 'description' => 'Sample description.'],
                    'ar' => ['name' => $name, 'description' => 'وصف تجريبي.'],
                ], ['name', 'description'], 'name');

                $ids[$key] = $technology->id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $technologies
     */
    private function services(array $technologies): void
    {
        $services = [
            ['web-development', ServiceTier::Core, 'globe', ['Web Development', 'تطوير المواقع'], ['تطوير-المواقع'], ['laravel', 'php', 'typescript']],
            ['mobile-app-development', ServiceTier::Core, 'smartphone', ['Mobile App Development', 'تطوير تطبيقات الجوال'], ['تطوير-تطبيقات-الجوال'], ['flutter']],
            ['ai-integration', ServiceTier::Core, 'sparkles', ['AI Integration', 'تكامل الذكاء الاصطناعي'], ['تكامل-الذكاء-الاصطناعي'], ['python']],
            ['api-development', ServiceTier::Specialist, 'plug', ['API Development', 'تطوير واجهات API'], ['تطوير-واجهات-api'], ['laravel']],
            ['cloud-devops', ServiceTier::Specialist, 'cloud', ['Cloud & DevOps', 'الحوسبة السحابية و DevOps'], ['الحوسبة-السحابية'], []],
        ];

        foreach ($services as $position => [$slug, $tier, $icon, $titles, $arSlug, $stack]) {
            if (Service::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $slug))->exists()) {
                continue;
            }

            $service = Service::create([
                'tier' => $tier,
                'icon' => $icon,
                'status' => ContentStatus::Published,
                'published_at' => now(),
                'sort_order' => $position,
            ]);

            $service->syncTranslations([
                'en' => ['title' => $titles[0], 'slug' => $slug, 'summary' => 'Sample content — replace in Admin → Services.', 'body' => "## Sample overview\n\nThis is demo text for design review."],
                'ar' => ['title' => $titles[1], 'slug' => $arSlug[0], 'summary' => 'محتوى تجريبي — استبدله من لوحة التحكم.', 'body' => "## نظرة عامة تجريبية\n\nهذا نص تجريبي للمراجعة."],
            ], Service::TRANSLATED_FIELDS, 'title');

            foreach ([['Discovery workshop', 'ورشة استكشاف'], ['Production-ready delivery', 'تسليم جاهز للإنتاج']] as $order => [$en, $ar]) {
                $service->features()->create(['sort_order' => $order])
                    ->syncTranslations(['en' => ['title' => $en], 'ar' => ['title' => $ar]], ['title', 'description'], 'title');
            }

            $service->faqs()->create(['sort_order' => 0])->syncTranslations([
                'en' => ['question' => 'Is this sample content?', 'answer' => 'Yes. Replace it in the admin.'],
                'ar' => ['question' => 'هل هذا محتوى تجريبي؟', 'answer' => 'نعم. استبدله من لوحة التحكم.'],
            ], ['question', 'answer'], 'question');

            $service->technologies()->sync(array_values(array_intersect_key($technologies, array_flip($stack))));
        }
    }

    private function industries(): void
    {
        $industries = [
            ['healthcare', 'الرعاية-الصحية', 'heart-pulse', ['Healthcare', 'الرعاية الصحية'], [['Clinic management', 'Patient apps'], ['إدارة العيادات', 'تطبيقات المرضى']]],
            ['education', 'التعليم', 'graduation-cap', ['Education', 'التعليم'], [['LMS', 'School ERP'], ['منصات التعلم', 'أنظمة المدارس']]],
        ];

        foreach ($industries as $position => [$slug, $arSlug, $icon, $names, $solutions]) {
            if (Industry::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $slug))->exists()) {
                continue;
            }

            Industry::create(['icon' => $icon, 'status' => ContentStatus::Published, 'published_at' => now(), 'sort_order' => $position])
                ->syncTranslations([
                    'en' => ['name' => $names[0], 'slug' => $slug, 'summary' => 'Sample content — replace in Admin → Industries.', 'typical_solutions' => $solutions[0]],
                    'ar' => ['name' => $names[1], 'slug' => $arSlug, 'summary' => 'محتوى تجريبي — استبدله من لوحة التحكم.', 'typical_solutions' => $solutions[1]],
                ], Industry::TRANSLATED_FIELDS, 'name');
        }
    }

    /**
     * Sample case studies. Client names stay confidential and every text says it is sample content.
     *
     * @param  array<string, int>  $technologies
     */
    private function portfolio(array $technologies): void
    {
        $categories = [];

        foreach ([['saas', 'SaaS', 'برمجيات-سحابية', 'برمجيات سحابية'], ['mobile', 'Mobile', 'تطبيقات-الجوال', 'تطبيقات الجوال'], ['e-commerce', 'E-commerce', 'التجارة-الإلكترونية', 'التجارة الإلكترونية']] as $position => [$slug, $name, $arSlug, $arName]) {
            $category = PortfolioCategory::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $slug))->first()
                ?? PortfolioCategory::create(['sort_order' => $position]);
            $category->syncTranslations(['en' => ['name' => $name, 'slug' => $slug], 'ar' => ['name' => $arName, 'slug' => $arSlug]], PortfolioCategory::TRANSLATED_FIELDS, 'name');
            $categories[$slug] = $category->id;
        }

        $industries = Industry::query()->with('translations')->get()
            ->mapWithKeys(fn (Industry $industry): array => [$industry->exactTranslation('en')?->slug => $industry->id]);

        $projects = [
            ['sample-clinic-booking-platform', 'saas', ProjectType::Saas, 'healthcare', true, ['Sample clinic booking platform', 'منصة حجز عيادات تجريبية'], 'منصة-حجز-عيادات-تجريبية', ['laravel', 'flutter']],
            ['sample-learning-portal', 'saas', ProjectType::Web, 'education', false, ['Sample bilingual learning portal', 'بوابة تعليمية ثنائية اللغة تجريبية'], 'بوابة-تعليمية-تجريبية', ['laravel', 'typescript']],
            ['sample-delivery-tracking-app', 'mobile', ProjectType::Mobile, null, false, ['Sample delivery tracking app', 'تطبيق تتبع توصيل تجريبي'], 'تطبيق-تتبع-توصيل-تجريبي', ['flutter', 'python']],
        ];

        foreach ($projects as $position => [$slug, $category, $type, $industry, $featured, $titles, $arSlug, $stack]) {
            if (PortfolioProject::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $slug))->exists()) {
                continue;
            }

            $project = PortfolioProject::create([
                'portfolio_category_id' => $categories[$category],
                'industry_id' => $industry ? ($industries[$industry] ?? null) : null,
                'type' => $type,
                'client_name' => 'Sample client (not real)',
                'is_client_public' => false,
                'project_date' => now()->subMonths(4 + $position * 5)->toDateString(),
                'is_featured' => $featured,
                'status' => ContentStatus::Published,
                'published_at' => now(),
                'sort_order' => $position,
            ]);

            $project->syncTranslations([
                'en' => [
                    'title' => $titles[0], 'slug' => $slug, 'summary' => 'Sample case study — replace in Admin → Portfolio.',
                    'challenge' => 'Sample challenge text for design review.', 'solution' => 'Sample solution text for design review.',
                    'results' => "- Sample result one\n- Sample result two", 'key_facts' => [['label' => 'Timeline', 'value' => 'Sample: 12 weeks']],
                ],
                'ar' => [
                    'title' => $titles[1], 'slug' => $arSlug, 'summary' => 'دراسة حالة تجريبية — استبدلها من لوحة التحكم.',
                    'challenge' => 'نص تجريبي للتحدي.', 'solution' => 'نص تجريبي للحل.',
                    'results' => "- نتيجة تجريبية أولى\n- نتيجة تجريبية ثانية", 'key_facts' => [['label' => 'المدة', 'value' => 'تجريبي: 12 أسبوعاً']],
                ],
            ], PortfolioProject::TRANSLATED_FIELDS, 'title');

            $project->technologies()->sync(array_values(array_intersect_key($technologies, array_flip($stack))));
        }
    }

    /**
     * Sample software products with a version history. No prices are stored.
     *
     * @param  array<string, int>  $technologies
     */
    private function products(array $technologies): void
    {
        $products = [
            ['sample-erp', 'layers', ['Sample ERP', 'نظام ERP تجريبي'], 'نظام-erp-تجريبي', ProductAvailability::Available, [PricingModel::OneTimeLicense, PricingModel::Subscription, PricingModel::CustomQuote]],
            ['sample-queue-manager', 'users', ['Sample Queue Manager', 'نظام إدارة طوابير تجريبي'], 'نظام-طوابير-تجريبي', ProductAvailability::Beta, [PricingModel::Subscription]],
        ];

        foreach ($products as $position => [$slug, $icon, $names, $arSlug, $availability, $models]) {
            if (Product::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $slug))->exists()) {
                continue;
            }

            $product = Product::create([
                'icon' => $icon,
                'pricing_models' => $models,
                'availability' => $availability,
                'demo_enabled' => true,
                'status' => ContentStatus::Published,
                'published_at' => now(),
                'sort_order' => $position,
            ]);

            $product->syncTranslations([
                'en' => [
                    'name' => $names[0], 'slug' => $slug, 'tagline' => 'Sample product — replace in Admin → Software products.',
                    'description' => 'This is demo text for design review.',
                    'features' => [['title' => 'Sample feature one', 'description' => 'Demo description.'], ['title' => 'Sample feature two', 'description' => null]],
                    'licensing_notes' => 'Sample licensing note. Quotes are preliminary.',
                ],
                'ar' => [
                    'name' => $names[1], 'slug' => $arSlug, 'tagline' => 'منتج تجريبي — استبدله من لوحة التحكم.',
                    'description' => 'هذا نص تجريبي للمراجعة.',
                    'features' => [['title' => 'ميزة تجريبية أولى', 'description' => 'وصف تجريبي.'], ['title' => 'ميزة تجريبية ثانية', 'description' => null]],
                    'licensing_notes' => 'ملاحظة ترخيص تجريبية. الأسعار مبدئية.',
                ],
            ], Product::TRANSLATED_FIELDS, 'name');

            foreach ([['1.0', 12, '- Sample first release', '- إصدار أول تجريبي'], ['1.1', 4, '- Sample improvements', '- تحسينات تجريبية']] as [$number, $monthsAgo, $en, $ar]) {
                $product->versions()->create(['version' => $number, 'released_at' => now()->subMonths($monthsAgo)->toDateString()])
                    ->syncTranslations(['en' => ['changelog' => $en], 'ar' => ['changelog' => $ar]], ['changelog'], 'changelog');
            }

            $product->technologies()->sync(array_values(array_intersect_key($technologies, array_flip(['laravel', 'php']))));
        }
    }
}
