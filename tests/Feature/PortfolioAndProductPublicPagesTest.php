<?php

use App\Enums\PricingModel;
use App\Models\Industry;
use App\Models\Media;
use App\Models\PortfolioCategory;
use App\Models\PortfolioProject;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Service;
use App\Models\Technology;

test('the portfolio index lists public projects with a featured project, tabs and hreflang', function () {
    $saas = PortfolioCategory::factory()->withTranslation('en', ['name' => 'SaaS', 'slug' => 'saas'])->create();
    PortfolioProject::factory()->featured()->withTranslation('en', ['title' => 'Featured Clinic'])->create(['portfolio_category_id' => $saas->id]);
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Grid Project'])->create();
    PortfolioProject::factory()->draft()->withTranslation('en', ['title' => 'Secret Draft'])->create();
    PortfolioProject::factory()->unlisted()->withTranslation('en', ['title' => 'Hidden Unlisted'])->create();
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Trashed Project'])->create()->delete();

    $this->get('/en/portfolio')
        ->assertSuccessful()
        ->assertSee('<html lang="en" dir="ltr"', false)
        ->assertSeeInOrder(['Featured', 'Featured Clinic', 'Grid Project'])
        ->assertSee(route('portfolio.index', ['locale' => 'en', 'category' => 'saas']), false)
        ->assertSee('Request a similar solution')
        ->assertSee('hreflang="ar"', false)
        ->assertDontSee('Secret Draft')
        ->assertDontSee('Hidden Unlisted')
        ->assertDontSee('Trashed Project');
});

test('the portfolio renders right to left in arabic with arabic projects only', function () {
    PortfolioProject::factory()->withTranslation('ar', ['title' => 'منصة العيادات', 'slug' => 'منصة-العيادات'])->create();
    PortfolioProject::factory()->withTranslation('en', ['title' => 'English Only'])->create();

    $this->get('/ar/portfolio')
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('منصة العيادات')
        ->assertDontSee('English Only');
});

test('the portfolio filters by category, industry, technology and search', function () {
    $mobile = PortfolioCategory::factory()->withTranslation('en', ['slug' => 'mobile'])->create();
    $health = Industry::factory()->withTranslation('en', ['name' => 'Healthcare', 'slug' => 'healthcare'])->create();
    $flutter = Technology::factory()->withTranslation('en', ['name' => 'Flutter'])->create(['key' => 'flutter']);
    $app = PortfolioProject::factory()->withTranslation('en', ['title' => 'Delivery App', 'summary' => 'Courier tracking'])->create(['portfolio_category_id' => $mobile->id]);
    $app->technologies()->attach($flutter);
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Clinic Portal', 'summary' => 'Bookings 100% online'])->create(['industry_id' => $health->id]);

    $this->get('/en/portfolio?category=mobile')->assertSee('Delivery App')->assertDontSee('Clinic Portal');
    $this->get('/en/portfolio?industry=healthcare')->assertSee('Clinic Portal')->assertDontSee('Delivery App')->assertSee('<option value="healthcare" selected', false);
    $this->get('/en/portfolio?technology=flutter')->assertSee('Delivery App')->assertDontSee('Clinic Portal')->assertSee('Projects built with Flutter');
    $this->get('/en/portfolio?q=courier')->assertSee('Delivery App')->assertDontSee('Clinic Portal');
    $this->get('/en/portfolio?q=%25')->assertSee('Clinic Portal')->assertDontSee('Delivery App');
    $this->get('/en/portfolio?q=nothing-matches')->assertSuccessful()->assertSee('No projects match your filters');
});

test('the portfolio paginates', function () {
    PortfolioProject::factory()->count(10)->withTranslation('en')->create();

    $this->get('/en/portfolio')->assertSuccessful()->assertSee('Showing 1–9 of 10');
    $this->get('/en/portfolio?page=2')->assertSuccessful()->assertSee('Showing 10–10 of 10');
});

test('a project page shows badges, safe links, ordered gallery, sanitized sections, facts and related work', function () {
    $category = PortfolioCategory::factory()->withTranslation('en', ['name' => 'SaaS', 'slug' => 'saas'])->create();
    $industry = Industry::factory()->withTranslation('en', ['name' => 'Healthcare'])->create();
    $laravel = Technology::factory()->expertise()->withTranslation('en', ['name' => 'Laravel'])->create(['key' => 'laravel']);
    $service = Service::factory()->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web-development'])->create();
    [$first, $second] = Media::factory()->count(2)->create()->each(
        fn (Media $media) => $media->variants()->create(['format' => 'webp', 'width' => 640, 'height' => 400, 'disk' => 'public', 'path' => "media/{$media->uuid}/640.webp", 'size' => 1000])
    )->all();

    $project = PortfolioProject::factory()
        ->withTranslation('en', [
            'title' => 'ClinicFlow',
            'slug' => 'clinicflow',
            'challenge' => "<script>alert('x')</script>Phone bookings.",
            'results' => "## Outcome\n\n[bad](javascript:alert(1))",
            'key_facts' => [['label' => 'Timeline', 'value' => '14 weeks'], null],
        ])
        ->withTranslation('ar', ['title' => 'كلينك فلو', 'slug' => 'كلينك-فلو'])
        ->create([
            'type' => 'saas',
            'portfolio_category_id' => $category->id,
            'industry_id' => $industry->id,
            'client_name' => 'Acme Secret Clinics',
            'is_client_public' => false,
            'live_url' => 'https://clinic.example.com',
            'ios_url' => 'https://apps.apple.com/app/id1',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);
    $project->technologies()->attach($laravel);
    $project->services()->attach($service);
    $project->syncMediaCollection('gallery', [$second->id, $first->id]);
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Sibling Project'])->create(['portfolio_category_id' => $category->id]);
    PortfolioProject::factory()->withTranslation('en', ['title' => 'Unrelated Project'])->create();

    $response = $this->get('/en/portfolio/clinicflow')
        ->assertSuccessful()
        ->assertSee('SaaS Platform')
        ->assertSee('Healthcare')
        ->assertSee('href="https://clinic.example.com" class=', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('App Store')
        ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
        ->assertDontSee("<script>alert('x')</script>", false)
        ->assertDontSee('javascript:alert', false)
        ->assertSee('<h2>Outcome</h2>', false)
        ->assertSee('Confidential client')
        ->assertDontSee('Acme Secret Clinics')
        ->assertSee('Timeline')
        ->assertSee('14 weeks')
        ->assertSee('data-level="verified_in_projects"', false)
        ->assertSee(route('portfolio.index', ['locale' => 'en', 'technology' => 'laravel']), false)
        ->assertSee(route('services.show', ['locale' => 'en', 'slug' => 'web-development']), false)
        ->assertSee('Sibling Project')
        ->assertDontSee('Unrelated Project')
        ->assertSee('hreflang="ar"', false)
        ->assertDontSee('name="robots"', false);

    $html = $response->getContent();
    expect(strpos($html, $second->uuid))->not->toBeFalse()
        ->and(strpos($html, $second->uuid))->toBeLessThan(strpos($html, $first->uuid));

    $this->get('/ar/portfolio/'.rawurlencode('كلينك-فلو'))
        ->assertSuccessful()
        ->assertSee('dir="rtl"', false)
        ->assertSee('كلينك فلو');
});

test('a client name is shown only with the client’s consent', function () {
    PortfolioProject::factory()->withTranslation('en', ['slug' => 'named'])->create(['client_name' => 'Proud Client Co', 'is_client_public' => true]);

    $this->get('/en/portfolio/named')->assertSee('Proud Client Co')->assertDontSee('Confidential client');
});

test('draft, trashed and other-language projects are not found while unlisted ones are noindex', function () {
    PortfolioProject::factory()->draft()->withTranslation('en', ['slug' => 'draft-project'])->create();
    PortfolioProject::factory()->withTranslation('en', ['slug' => 'gone'])->create()->delete();
    PortfolioProject::factory()->withTranslation('en', ['slug' => 'english-only'])->create();
    PortfolioProject::factory()->unlisted()->withTranslation('en', ['slug' => 'by-link'])->create();

    $this->get('/en/portfolio/draft-project')->assertNotFound();
    $this->get('/en/portfolio/gone')->assertNotFound();
    $this->get('/ar/portfolio/english-only')->assertNotFound();
    $this->get('/en/portfolio/by-link')->assertSuccessful()->assertSee('<meta name="robots" content="noindex">', false);
});

test('service and industry pages list related public projects', function () {
    $service = Service::factory()->withTranslation('en', ['slug' => 'web-development'])->create();
    $industry = Industry::factory()->withTranslation('en', ['name' => 'Healthcare', 'slug' => 'healthcare'])->create();
    $project = PortfolioProject::factory()->withTranslation('en', ['title' => 'ClinicFlow Case'])->create(['industry_id' => $industry->id]);
    $project->services()->attach($service);
    $draft = PortfolioProject::factory()->draft()->withTranslation('en', ['title' => 'Draft Case'])->create(['industry_id' => $industry->id]);
    $draft->services()->attach($service);

    $this->get('/en/services/web-development')
        ->assertSuccessful()
        ->assertSee('Projects delivered with this service')
        ->assertSee('ClinicFlow Case')
        ->assertDontSee('Draft Case');

    $this->get('/en/industries')
        ->assertSuccessful()
        ->assertSee('ClinicFlow Case')
        ->assertDontSee('Draft Case')
        ->assertSee(route('portfolio.index', ['locale' => 'en', 'industry' => 'healthcare']), false);
});

test('the products index lists published products with availability and demo requests', function () {
    Product::factory()->withTranslation('en', ['name' => 'FanzERP', 'features' => [['title' => 'Multi-branch POS', 'description' => null]]])->create();
    Product::factory()->withTranslation('en', ['name' => 'QueueBeta'])->create(['availability' => 'beta']);
    Product::factory()->draft()->withTranslation('en', ['name' => 'Draft Product'])->create();
    Product::factory()->withTranslation('ar', ['name' => 'منتج عربي', 'slug' => 'منتج-عربي'])->create();

    $this->get('/en/products')
        ->assertSuccessful()
        ->assertSee('FanzERP')
        ->assertSeeInOrder(['QueueBeta', 'Beta'])
        ->assertSee('Multi-branch POS')
        ->assertSee('Request a demo')
        ->assertSee(route('contact', ['locale' => 'en', 'topic' => 'demo', 'ref' => 'FanzERP']))
        ->assertSee('Contact sales')
        ->assertDontSee('Draft Product')
        ->assertDontSee('منتج عربي')
        ->assertSee('hreflang="ar"', false);

    $this->get('/ar/products')->assertSuccessful()->assertSee('dir="rtl"', false)->assertSee('منتج عربي')->assertDontSee('FanzERP');
});

test('a product page shows version, licensing models without prices, features and a sanitized changelog', function () {
    $product = Product::factory()
        ->withTranslation('en', [
            'name' => 'FanzERP',
            'slug' => 'fanzerp',
            'description' => '<img src=x onerror=alert(1)>Retail ERP.',
            'features' => [['title' => 'Inventory & warehouses', 'description' => 'Multi-warehouse stock.'], null],
            'licensing_notes' => 'Annual support available.',
        ])
        ->withTranslation('ar', ['name' => 'فانز ERP', 'slug' => 'فانز-erp'])
        ->create([
            'pricing_models' => [PricingModel::OneTimeLicense, PricingModel::Subscription],
            'docs_url' => 'https://docs.example.com',
        ]);
    ProductVersion::factory()->for($product)->withTranslation('en', ['changelog' => "- Offline POS sync\n- <script>bad()</script>"])->create(['version' => '2.3', 'released_at' => '2026-11-01']);
    ProductVersion::factory()->for($product)->withTranslation('en', ['changelog' => '- Multi-currency'])->create(['version' => '2.2', 'released_at' => '2026-08-01']);

    $this->get('/en/products/fanzerp')
        ->assertSuccessful()
        ->assertSee('v2.3')
        ->assertSeeInOrder(['One-time license', 'Cloud subscription'])
        ->assertSee('preliminary')
        ->assertSee('Inventory &amp; warehouses', false)
        ->assertSee('Annual support available.')
        ->assertSeeInOrder(['Offline POS sync', 'Multi-currency'])
        ->assertDontSee('<script>bad()</script>', false)
        ->assertDontSee('onerror', false)
        ->assertSee('href="https://docs.example.com" target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('id="request-demo"', false)
        ->assertDontSee('Custom quote')
        ->assertDontSeeText('$')
        ->assertSee('hreflang="ar"', false);

    $this->get('/ar/products/'.rawurlencode('فانز-erp'))->assertSuccessful()->assertSee('dir="rtl"', false)->assertSee('فانز ERP');
});

test('draft and trashed products are not found', function () {
    Product::factory()->draft()->withTranslation('en', ['slug' => 'draft-product'])->create();
    Product::factory()->withTranslation('en', ['slug' => 'gone'])->create()->delete();

    $this->get('/en/products/draft-product')->assertNotFound();
    $this->get('/en/products/gone')->assertNotFound();
});

test('portfolio and product routes win over the cms page catch-all', function () {
    $this->get('/en/portfolio')->assertSuccessful();
    $this->get('/en/products')->assertSuccessful();
});
