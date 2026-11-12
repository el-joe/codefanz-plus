<?php

namespace Database\Seeders;

use App\Enums\BudgetRange;
use App\Enums\ContentStatus;
use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use App\Enums\CustomerUserRole;
use App\Enums\EmploymentType;
use App\Enums\LeadActivityType;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\PageSectionType;
use App\Enums\PageStatus;
use App\Enums\PricingModel;
use App\Enums\ProductAvailability;
use App\Enums\ProjectType;
use App\Enums\Role;
use App\Enums\ServiceTier;
use App\Enums\TechnologyProficiency;
use App\Models\Career;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Industry;
use App\Models\Lead;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageTranslation;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\Product;
use App\Models\Service;
use App\Models\Statistic;
use App\Models\Technology;
use App\Models\TechnologyCategory;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
        $this->testimonials();
        $this->faqs();
        $this->statistics();
        $this->careers();
        $this->menus();
        $this->homeSections();
        $this->aboutAndCareersSections();
        $this->legalPages();
        $this->leads();
        $this->customers();
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

    /**
     * Clearly-sample quotes. Consent is flagged so they render in demos; never reuse them as real testimonials.
     */
    private function testimonials(): void
    {
        if (Testimonial::query()->where('author_name', 'like', 'Sample client%')->exists()) {
            return;
        }

        $project = PortfolioProject::query()->whereHas('translations', fn ($query) => $query->where('slug', 'sample-clinic-booking-platform'))->value('id');

        $quotes = [
            ['Sample client A', 'Operations Director', 'Sample: the new platform replaced our spreadsheets.', 'تجريبي: المنصة الجديدة استبدلت جداول البيانات لدينا.', 'مدير العمليات', $project],
            ['Sample client B', 'Founder', 'Sample: weekly demos kept everyone aligned.', 'تجريبي: العروض الأسبوعية أبقت الجميع على اطلاع.', 'المؤسس', null],
            ['Sample client C', 'Product Manager', 'Sample: the Arabic experience feels native.', 'تجريبي: التجربة العربية تبدو أصلية.', 'مدير المنتج', null],
        ];

        foreach ($quotes as $position => [$name, $role, $en, $ar, $arRole, $projectId]) {
            Testimonial::create([
                'portfolio_project_id' => $projectId,
                'author_name' => $name,
                'company' => 'Sample company (not real)',
                'consent_confirmed' => true,
                'consent_confirmed_at' => now(),
                'status' => ContentStatus::Published,
                'published_at' => now(),
                'is_featured' => true,
                'sort_order' => $position,
            ])->syncTranslations([
                'en' => ['quote' => $en, 'author_role' => $role],
                'ar' => ['quote' => $ar, 'author_role' => $arRole],
            ], Testimonial::TRANSLATED_FIELDS, 'quote');
        }
    }

    private function faqs(): void
    {
        if (FaqCategory::query()->exists()) {
            return;
        }

        $groups = [
            [['Projects & pricing', 'المشاريع والأسعار'], [
                ['Sample: how much does a project cost?', 'It depends on scope. This is sample text — replace it in Admin → FAQ.', 'تجريبي: كم تكلفة المشروع؟', 'يعتمد ذلك على النطاق. هذا نص تجريبي.'],
                ['Sample: how long does an MVP take?', 'Sample answer for design review.', 'تجريبي: كم يستغرق بناء النسخة الأولى؟', 'إجابة تجريبية للمراجعة.'],
            ]],
            [['Support', 'الدعم'], [
                ['Sample: what happens after launch?', 'Sample answer for design review.', 'تجريبي: ماذا يحدث بعد الإطلاق؟', 'إجابة تجريبية للمراجعة.'],
            ]],
        ];

        foreach ($groups as $position => [$names, $questions]) {
            $category = FaqCategory::create(['sort_order' => $position]);
            $category->syncTranslations(['en' => ['name' => $names[0]], 'ar' => ['name' => $names[1]]], FaqCategory::TRANSLATED_FIELDS, 'name');

            foreach ($questions as $order => [$question, $answer, $arQuestion, $arAnswer]) {
                Faq::create(['faq_category_id' => $category->id, 'status' => ContentStatus::Published, 'published_at' => now(), 'is_featured' => true, 'sort_order' => $order])
                    ->syncTranslations([
                        'en' => ['question' => $question, 'answer' => $answer],
                        'ar' => ['question' => $arQuestion, 'answer' => $arAnswer],
                    ], Faq::TRANSLATED_FIELDS, 'question');
            }
        }
    }

    /**
     * Placeholder figures for layout review only — replace with real, verifiable numbers before launch.
     */
    private function statistics(): void
    {
        if (Statistic::query()->exists()) {
            return;
        }

        foreach ([[12, '+', 'Sample: projects delivered', 'تجريبي: مشاريع منجزة'], [3, null, 'Sample: countries served', 'تجريبي: دول نخدمها'], [95, '%', 'Sample: milestones on time', 'تجريبي: مراحل في موعدها']] as $position => [$value, $suffix, $en, $ar]) {
            Statistic::create(['value' => $value, 'status' => ContentStatus::Published, 'sort_order' => $position])
                ->syncTranslations(['en' => ['label' => $en, 'suffix' => $suffix], 'ar' => ['label' => $ar, 'suffix' => $suffix]], Statistic::TRANSLATED_FIELDS, 'label');
        }
    }

    private function careers(): void
    {
        $positions = [
            ['sample-laravel-developer', 'مطور-laravel-تجريبي', ['Sample Laravel Developer', 'مطور Laravel (تجريبي)'], ['Engineering', 'الهندسة'], EmploymentType::FullTime, false],
            ['sample-flutter-developer', 'مطور-flutter-تجريبي', ['Sample Flutter Developer', 'مطور Flutter (تجريبي)'], ['Mobile', 'تطبيقات الجوال'], EmploymentType::Contract, true],
        ];

        foreach ($positions as $position => [$slug, $arSlug, $titles, $departments, $type, $remote]) {
            if (Career::query()->whereHas('translations', fn ($query) => $query->where('locale', 'en')->where('slug', $slug))->exists()) {
                continue;
            }

            Career::create([
                'employment_type' => $type,
                'is_remote' => $remote,
                'status' => ContentStatus::Published,
                'published_at' => now(),
                'closes_at' => now()->addMonths(2)->toDateString(),
                'sort_order' => $position,
            ])->syncTranslations([
                'en' => [
                    'title' => $titles[0], 'slug' => $slug, 'department' => $departments[0], 'location' => 'Sample city',
                    'summary' => 'Sample position — replace in Admin → Careers.',
                    'responsibilities' => ['Sample responsibility one', 'Sample responsibility two'],
                    'requirements' => ['Sample requirement one', 'Sample requirement two'],
                    'body' => 'This is demo text for design review.',
                ],
                'ar' => [
                    'title' => $titles[1], 'slug' => $arSlug, 'department' => $departments[1], 'location' => 'مدينة تجريبية',
                    'summary' => 'وظيفة تجريبية — استبدلها من لوحة التحكم.',
                    'responsibilities' => ['مسؤولية تجريبية أولى', 'مسؤولية تجريبية ثانية'],
                    'requirements' => ['متطلب تجريبي أول', 'متطلب تجريبي ثانٍ'],
                    'body' => 'هذا نص تجريبي للمراجعة.',
                ],
            ], Career::TRANSLATED_FIELDS, 'title');
        }
    }

    private function menus(): void
    {
        $route = fn (string $name, array $labels): array => ['link_type' => MenuLinkType::Route, 'route_name' => $name, 'labels' => $labels];
        $services = Service::query()->published()->whereNull('parent_id')->where('tier', ServiceTier::Core)->ordered()->get();
        $serviceLinks = $services->map(fn (Service $service): array => [
            'link_type' => MenuLinkType::Linkable, 'linkable_type' => 'service', 'linkable_id' => $service->id, 'labels' => $service->translations()->pluck('title', 'locale')->all(),
        ])->all();

        $menus = [
            MenuLocation::Header->value => [
                [...$route('services.index', ['en' => 'Services', 'ar' => 'الخدمات']), 'children' => $serviceLinks],
                $route('portfolio.index', ['en' => 'Portfolio', 'ar' => 'أعمالنا']),
                $route('products.index', ['en' => 'Products', 'ar' => 'المنتجات']),
                $route('technologies.index', ['en' => 'Technologies', 'ar' => 'التقنيات']),
                $route('careers.index', ['en' => 'Careers', 'ar' => 'الوظائف']),
                $route('about', ['en' => 'About', 'ar' => 'من نحن']),
            ],
            MenuLocation::FooterServices->value => $serviceLinks,
            MenuLocation::FooterCompany->value => [
                $route('about', ['en' => 'About Us', 'ar' => 'من نحن']),
                $route('portfolio.index', ['en' => 'Portfolio', 'ar' => 'أعمالنا']),
                $route('careers.index', ['en' => 'Careers', 'ar' => 'الوظائف']),
                $route('testimonials.index', ['en' => 'Testimonials', 'ar' => 'آراء العملاء']),
                $route('contact', ['en' => 'Contact', 'ar' => 'تواصل معنا']),
            ],
            MenuLocation::FooterResources->value => [
                $route('products.index', ['en' => 'Products', 'ar' => 'المنتجات']),
                $route('technologies.index', ['en' => 'Technologies', 'ar' => 'التقنيات']),
                $route('industries.index', ['en' => 'Industries', 'ar' => 'القطاعات']),
                $route('faq.index', ['en' => 'FAQ', 'ar' => 'الأسئلة الشائعة']),
            ],
        ];

        foreach ($menus as $location => $items) {
            $menu = Menu::for(MenuLocation::from($location));

            if ($menu->items()->exists()) {
                continue;
            }

            foreach ($items as $position => $item) {
                $parent = $this->menuItem($menu, $item, $position, null);

                foreach ($item['children'] ?? [] as $order => $child) {
                    $this->menuItem($menu, $child, $order, $parent->id);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function menuItem(Menu $menu, array $item, int $position, ?int $parentId): MenuItem
    {
        $menuItem = MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => $parentId,
            'link_type' => $item['link_type'],
            'route_name' => $item['route_name'] ?? null,
            'linkable_type' => $item['linkable_type'] ?? null,
            'linkable_id' => $item['linkable_id'] ?? null,
            'sort_order' => $position,
        ]);
        $menuItem->syncTranslations(collect($item['labels'])->map(fn (string $label): array => ['label' => $label])->all(), MenuItem::TRANSLATED_FIELDS, 'label');

        return $menuItem;
    }

    /**
     * Sample copy for sections that have no catalog source (process steps, reasons, highlights).
     */
    private function homeSections(): void
    {
        PageSection::ensureHomeSections();

        $copy = [
            PageSectionType::Hero->value => [
                'en' => ['points' => ['Sample: free preliminary estimate', 'Sample: Arabic & English teams']],
                'ar' => ['points' => ['تجريبي: تقدير مبدئي مجاني', 'تجريبي: فرق عربية وإنجليزية']],
            ],
            PageSectionType::Process->value => [
                'en' => ['items' => [['title' => 'Discover', 'text' => 'Sample step description.'], ['title' => 'Design', 'text' => 'Sample step description.'], ['title' => 'Build', 'text' => 'Sample step description.'], ['title' => 'Launch', 'text' => 'Sample step description.']]],
                'ar' => ['items' => [['title' => 'الاستكشاف', 'text' => 'وصف تجريبي.'], ['title' => 'التصميم', 'text' => 'وصف تجريبي.'], ['title' => 'التطوير', 'text' => 'وصف تجريبي.'], ['title' => 'الإطلاق', 'text' => 'وصف تجريبي.']]],
            ],
            PageSectionType::WhyUs->value => [
                'en' => ['items' => [['title' => 'Security by default', 'text' => 'Sample reason text.'], ['title' => 'You own the code', 'text' => 'Sample reason text.']], 'points' => ['Sample promise one', 'Sample promise two']],
                'ar' => ['items' => [['title' => 'الأمان أولاً', 'text' => 'نص تجريبي.'], ['title' => 'الكود ملكك', 'text' => 'نص تجريبي.']], 'points' => ['وعد تجريبي أول', 'وعد تجريبي ثانٍ']],
            ],
        ];

        foreach ($copy as $type => $locales) {
            $section = PageSection::query()->home()->where('type', $type)->first();

            if ($section === null || $section->translations()->exists()) {
                continue;
            }

            foreach ($locales as $locale => $values) {
                $section->translations()->create(['locale' => $locale, ...$values]);
            }
        }
    }

    /**
     * Sample About copy (story, values, disciplines) and careers perks, clearly marked as samples.
     */
    private function aboutAndCareersSections(): void
    {
        PageSection::ensureSections(PageSection::ABOUT);
        PageSection::ensureSections(PageSection::CAREERS);

        $copy = [
            PageSectionType::AboutStory->value => [
                'en' => ['subheading' => 'Sample story text — replace it in Admin → Page sections → About page.'],
                'ar' => ['subheading' => 'نص تجريبي لقصتنا — استبدله من لوحة التحكم ← أقسام الصفحات ← صفحة من نحن.'],
            ],
            PageSectionType::AboutValues->value => [
                'en' => ['items' => [['title' => 'Integrity', 'text' => 'Sample value description.'], ['title' => 'Craftsmanship', 'text' => 'Sample value description.'], ['title' => 'Partnership', 'text' => 'Sample value description.'], ['title' => 'Continuous learning', 'text' => 'Sample value description.']]],
                'ar' => ['items' => [['title' => 'النزاهة', 'text' => 'وصف تجريبي.'], ['title' => 'الإتقان', 'text' => 'وصف تجريبي.'], ['title' => 'الشراكة', 'text' => 'وصف تجريبي.'], ['title' => 'التعلم المستمر', 'text' => 'وصف تجريبي.']]],
            ],
            PageSectionType::AboutTeam->value => [
                'en' => ['items' => [['title' => 'Product & UX design', 'text' => 'Research, flows, prototypes'], ['title' => 'Backend engineering', 'text' => 'APIs, data, integrations'], ['title' => 'Frontend & mobile', 'text' => 'Web, Android, iOS'], ['title' => 'QA & DevOps', 'text' => 'Testing, CI/CD, monitoring'], ['title' => 'AI engineering', 'text' => 'LLMs, automation, evaluation'], ['title' => 'Project management', 'text' => 'Planning, demos, reporting']]],
                'ar' => ['items' => [['title' => 'تصميم المنتج وتجربة المستخدم', 'text' => 'بحث، مسارات، نماذج أولية'], ['title' => 'هندسة الخوادم', 'text' => 'واجهات برمجية، بيانات، تكاملات'], ['title' => 'الواجهات والجوال', 'text' => 'ويب، أندرويد، iOS'], ['title' => 'الجودة وDevOps', 'text' => 'اختبارات، CI/CD، مراقبة'], ['title' => 'هندسة الذكاء الاصطناعي', 'text' => 'نماذج لغوية، أتمتة، تقييم'], ['title' => 'إدارة المشاريع', 'text' => 'تخطيط، عروض، تقارير']]],
            ],
            PageSectionType::CareersPerks->value => [
                'en' => ['items' => [['title' => 'Remote-friendly', 'text' => 'Sample perk description.'], ['title' => 'Grow fast', 'text' => 'Sample perk description.'], ['title' => 'Healthy pace', 'text' => 'Sample perk description.'], ['title' => 'Modern stack', 'text' => 'Sample perk description.']]],
                'ar' => ['items' => [['title' => 'عمل عن بُعد', 'text' => 'وصف تجريبي.'], ['title' => 'تطوّر سريع', 'text' => 'وصف تجريبي.'], ['title' => 'إيقاع صحي', 'text' => 'وصف تجريبي.'], ['title' => 'تقنيات حديثة', 'text' => 'وصف تجريبي.']]],
            ],
        ];

        foreach ($copy as $type => $locales) {
            $section = PageSection::query()->where('type', $type)->first();

            if ($section === null || $section->translations()->exists()) {
                continue;
            }

            foreach ($locales as $locale => $values) {
                $section->translations()->create(['locale' => $locale, ...$values]);
            }
        }
    }

    /**
     * Draft Privacy Policy and Terms skeletons (legal template). They stay drafts until legal review.
     */
    private function legalPages(): void
    {
        $draftEn = '**Draft — requires legal review.** Replace this placeholder before publishing.';
        $draftAr = '**مسودة — تتطلب مراجعة قانونية.** استبدل هذا النص قبل النشر.';
        $clauses = fn (array $headings, string $draft): string => collect($headings)->map(fn (string $heading): string => "## {$heading}\n\n{$draft}")->implode("\n\n");

        $pages = [
            [
                'en' => ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'headings' => ['Information we collect', 'How we use it', 'AI assistant conversations', 'Cookies & analytics', 'Your rights', 'Data retention & security']],
                'ar' => ['title' => 'سياسة الخصوصية', 'slug' => 'سياسة-الخصوصية', 'headings' => ['المعلومات التي نجمعها', 'كيف نستخدمها', 'محادثات المساعد الذكي', 'ملفات تعريف الارتباط والتحليلات', 'حقوقك', 'الاحتفاظ بالبيانات وأمنها']],
            ],
            [
                'en' => ['title' => 'Terms & Conditions', 'slug' => 'terms', 'headings' => ['Scope', 'Quotes and proposals', 'Payments', 'Intellectual property', 'Liability', 'Changes to these terms']],
                'ar' => ['title' => 'الشروط والأحكام', 'slug' => 'الشروط-والأحكام', 'headings' => ['النطاق', 'العروض والتقديرات', 'المدفوعات', 'الملكية الفكرية', 'المسؤولية', 'التعديلات على هذه الشروط']],
            ],
        ];

        foreach ($pages as $translations) {
            if (PageTranslation::query()->where('locale', 'en')->where('slug', $translations['en']['slug'])->exists()) {
                continue;
            }

            $page = Page::create(['template' => 'legal', 'status' => PageStatus::Draft]);

            foreach ($translations as $locale => $values) {
                $page->translations()->create([
                    'locale' => $locale,
                    'title' => $values['title'],
                    'slug' => $values['slug'],
                    'body' => $clauses($values['headings'], $locale === 'ar' ? $draftAr : $draftEn),
                ]);
            }
        }
    }

    /**
     * Clearly-sample leads across the pipeline for the CRM board and table. Emails use example.test.
     */
    private function leads(): void
    {
        if (Lead::query()->where('email', 'like', '%@example.test')->exists()) {
            return;
        }

        $services = Service::query()->pluck('id');
        $samples = [
            ['Sample: Clinic booking platform lead', 'Sample Clinic Co.', LeadSource::QuoteForm, LeadStatus::New, LeadPriority::High, 1_200_000, 'ar'],
            ['Sample: Company website redesign lead', null, LeadSource::ContactForm, LeadStatus::New, LeadPriority::Normal, null, 'en'],
            ['Sample: Delivery app lead', 'Sample Logistics', LeadSource::QuoteForm, LeadStatus::Contacted, LeadPriority::Normal, 2_200_000, 'en'],
            ['Sample: LMS for a training centre', 'Sample Academy', LeadSource::Manual, LeadStatus::Qualified, LeadPriority::Normal, 1_500_000, 'ar'],
            ['Sample: ERP for a retail chain', 'Sample Retail', LeadSource::QuoteForm, LeadStatus::Proposal, LeadPriority::High, 4_850_000, 'en'],
            ['Sample: Fintech dashboard', 'Sample Finance', LeadSource::Manual, LeadStatus::Negotiation, LeadPriority::High, 3_000_000, 'en'],
            ['Sample: Restaurant ordering app', 'Sample Foods', LeadSource::QuoteForm, LeadStatus::Won, LeadPriority::Normal, 900_000, 'ar'],
            ['Sample: Marketing landing page', null, LeadSource::ContactForm, LeadStatus::Lost, LeadPriority::Low, null, 'en'],
        ];

        foreach ($samples as $index => [$name, $company, $source, $status, $priority, $value, $locale]) {
            $lead = Lead::create([
                'source' => $source,
                'status' => $status,
                'priority' => $priority,
                'name' => $name,
                'email' => 'lead'.($index + 1).'@example.test',
                'company' => $company,
                'country' => $locale === 'ar' ? 'EG' : 'AE',
                'preferred_locale' => $locale,
                'platforms' => ['web'],
                'description' => 'Sample lead for layout review only. Not a real request.',
                'budget_range' => BudgetRange::From10kTo25k,
                'estimated_value_minor' => $value,
                'currency' => $value ? 'USD' : null,
                'follow_up_at' => $status->isOpen() ? now()->addDays($index)->setTime(11, 0) : null,
                'lost_reason' => $status === LeadStatus::Lost ? 'Sample: budget not approved' : null,
                'consent_at' => now(),
                'locale' => $locale,
            ]);
            $lead->services()->sync($services->shuffle()->take(1)->all());
            $lead->activities()->create(['type' => LeadActivityType::System, 'meta' => ['event' => 'created', 'source' => $source->value]]);
        }
    }

    /**
     * A sample customer converted from the sample won lead, with one portal user (client.demo@example.test).
     * The password is random: sign in locally through "Forgot password" (see docs/DEPLOYMENT_GUIDE.md).
     */
    private function customers(): void
    {
        if (Customer::query()->where('email', 'customer.demo@example.test')->exists()) {
            return;
        }

        $lead = Lead::query()->where('status', LeadStatus::Won)->where('email', 'like', '%@example.test')->doesntHave('customer')->first();

        $customer = Customer::create([
            'type' => CustomerType::Company,
            'status' => CustomerStatus::Active,
            'company_name' => 'Sample Foods (demo customer)',
            'contact_name' => 'Sample Client',
            'email' => 'customer.demo@example.test',
            'country' => 'EG',
            'billing_city' => 'Cairo',
            'preferred_locale' => 'ar',
            'notes' => 'Sample customer for layout review only. Not a real account.',
            'created_from_lead_id' => $lead?->getKey(),
        ]);

        $user = User::query()->firstOrCreate(['email' => 'client.demo@example.test'], [
            'name' => 'Sample Client',
            'password' => Str::password(40),
            'locale' => 'ar',
            'timezone' => 'Africa/Cairo',
            'is_active' => true,
        ]);
        $user->syncRoles([Role::Client->value]);

        $customer->users()->syncWithoutDetaching([$user->getKey() => ['role' => CustomerUserRole::Owner, 'invited_at' => now()]]);
    }
}
