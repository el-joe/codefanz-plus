<?php

use App\Models\Career;
use App\Models\Faq;
use App\Models\Industry;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\Testimonial;
use App\Services\Security\OutboundUrlGuard;
use App\Services\Settings;
use Database\Seeders\KillSwitchSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed([LanguageSeeder::class, RolesAndPermissionsSeeder::class, KillSwitchSeeder::class]);
        // No real DNS in tests: every host name resolves to a public documentation address unless a test says otherwise.
        OutboundUrlGuard::resolveHostsUsing(fn (string $host): array => ['93.184.216.34']);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
| Payments (M6.2): Stripe test credentials in Settings, a faked Stripe API and signed webhook deliveries.
*/

function configureStripe(string $mode = 'test'): void
{
    $settings = app(Settings::class);
    $settings->set('payments', 'stripe_secret_key', 'sk_test_codefanz123', encrypted: true);
    $settings->set('payments', 'stripe_webhook_secret', 'whsec_codefanzsecret', encrypted: true);
    $settings->set('payments', 'stripe_mode', $mode);
}

/**
 * A Checkout Session as Stripe returns it (expanded to the balance transaction), paid by default.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stripeSession(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'cs_test_1',
        'object' => 'checkout.session',
        'status' => 'complete',
        'payment_status' => 'paid',
        'amount_total' => 150000,
        'currency' => 'usd',
        'livemode' => false,
        'customer_details' => ['email' => 'payer@example.test', 'name' => 'Payer'],
        'payment_intent' => [
            'id' => 'pi_test_1',
            'status' => 'succeeded',
            'latest_charge' => [
                'id' => 'ch_test_1',
                'created' => now()->getTimestamp(),
                'payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']],
                'balance_transaction' => ['fee' => 4380, 'currency' => 'usd', 'net' => 145620],
            ],
        ],
    ], $overrides);
}

/**
 * Fake the Stripe API: creating a session answers cs_test_1; retrieving one answers $session (or a status code).
 *
 * @param  array<string, mixed>|int  $session
 */
function fakeStripe(array|int $session = []): void
{
    Http::fake(function (Request $request) use ($session) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($request->method() === 'POST' && $path === '/v1/checkout/sessions') {
            return Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1', 'livemode' => false]);
        }

        if ($request->method() === 'GET' && str_starts_with($path, '/v1/checkout/sessions/')) {
            return is_int($session)
                ? Http::response(['error' => ['code' => 'api_error']], $session)
                : Http::response(stripeSession($session));
        }

        return Http::response(['error' => ['code' => 'unexpected']], 404);
    });
}

/**
 * POST a Stripe-signed webhook delivery.
 *
 * @param  array<string, mixed>  $event
 */
function postStripeWebhook(TestCase $test, array $event, ?int $timestamp = null, string $secret = 'whsec_codefanzsecret', ?string $signature = null): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $timestamp ??= now()->getTimestamp();
    $signature ??= hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return $test->call('POST', route('webhooks.payments', 'stripe'), server: [
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], content: $body);
}

/**
 * @param  array<string, mixed>  $object
 * @return array<string, mixed>
 */
function stripeEvent(string $type, string $sessionId = 'cs_test_1', string $eventId = 'evt_test_1', array $object = []): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'created' => now()->getTimestamp(),
        'livemode' => false,
        'data' => ['object' => ['id' => $sessionId, 'object' => 'checkout.session', 'status' => 'complete', 'payment_status' => 'paid', ...$object]],
    ];
}

/*
| Payments (M6.3): Paymob, PayTabs and Fawry test credentials in Settings.
*/

function configurePaymob(): void
{
    $settings = app(Settings::class);
    $settings->set('payments', 'paymob_secret_key', 'egy_sk_test_codefanz', encrypted: true);
    $settings->set('payments', 'paymob_public_key', 'egy_pk_test_codefanz');
    $settings->set('payments', 'paymob_api_key', 'paymobApiKey123', encrypted: true);
    $settings->set('payments', 'paymob_hmac_secret', 'PAYMOBHMACSECRET', encrypted: true);
    $settings->set('payments', 'paymob_integration_ids', '4097558, 4097559');
    $settings->set('payments', 'paymob_mode', 'test');
}

function configurePayTabs(string $region = 'EGY'): void
{
    $settings = app(Settings::class);
    $settings->set('payments', 'paytabs_profile_id', '98765');
    $settings->set('payments', 'paytabs_server_key', 'SJNPAYTABSKEY-123', encrypted: true);
    $settings->set('payments', 'paytabs_region', $region);
    $settings->set('payments', 'paytabs_mode', 'test');
}

function configureFawry(string $method = 'PAYATFAWRY'): void
{
    $settings = app(Settings::class);
    $settings->set('payments', 'fawry_merchant_code', 'FAWRYMERCHANT1');
    $settings->set('payments', 'fawry_secure_key', 'fawry-secure-key', encrypted: true);
    $settings->set('payments', 'fawry_payment_method', $method);
    $settings->set('payments', 'fawry_mode', 'test');
}

/*
| Public site audits (M11.2): one published bilingual record per public route type.
*/

/**
 * Every public route type (detail routes last). Safe to use in datasets: it touches no database.
 *
 * @return list<string>
 */
function publicSiteRouteNames(): array
{
    return ['home', 'services.index', 'technologies.index', 'industries.index', 'portfolio.index', 'products.index', 'careers.index',
        'blog.index', 'faq.index', 'testimonials.index', 'about', 'contact', 'quote', 'assistant',
        'services.show', 'portfolio.show', 'products.show', 'careers.show', 'blog.show', 'blog.category', 'blog.tag', 'pages.show'];
}

/**
 * Creates the content behind every public route type and returns, per route, its English and Arabic paths.
 *
 * @return array<string, array{en: string, ar: string}>
 */
function publicSiteRoutes(): array
{
    $service = Service::factory()->withTranslation('en', ['title' => 'Web Development', 'slug' => 'web-development'])->withTranslation('ar', ['title' => 'تطوير الويب', 'slug' => 'تطوير-الويب'])->create();
    PortfolioProject::factory()->withTranslation('en', ['title' => 'ClinicFlow', 'slug' => 'clinicflow'])->withTranslation('ar', ['title' => 'كلينك فلو', 'slug' => 'كلينك-فلو'])->create();
    Product::factory()->withTranslation('en', ['name' => 'FanzERP', 'slug' => 'fanz-erp'])->withTranslation('ar', ['name' => 'فانز إي آر بي', 'slug' => 'فانز-erp'])->create();
    Career::factory()->withTranslation('en', ['title' => 'Laravel Developer', 'slug' => 'laravel-developer'])->withTranslation('ar', ['title' => 'مطور لارافيل', 'slug' => 'مطور-لارافيل'])->create();
    Page::factory()->withTranslation('en', ['title' => 'Privacy Policy', 'slug' => 'privacy-policy'])->withTranslation('ar', ['title' => 'سياسة الخصوصية', 'slug' => 'سياسة-الخصوصية'])->create(['template' => 'legal']);
    Technology::factory()->withTranslation('en', ['name' => 'Laravel'])->withTranslation('ar', ['name' => 'Laravel'])->create();
    Industry::factory()->withTranslation('en', ['name' => 'Healthcare'])->withTranslation('ar', ['name' => 'الرعاية الصحية'])->create();
    Faq::factory()->withTranslation('en', ['question' => 'How long does a project take?', 'answer' => 'Usually eight to twelve weeks.'])->withTranslation('ar', ['question' => 'كم يستغرق المشروع؟', 'answer' => 'عادة من ثمانية إلى اثني عشر أسبوعًا.'])->create();
    Testimonial::factory()->withTranslation('en')->withTranslation('ar')->create();

    $category = PostCategory::factory()->withTranslation('en', ['name' => 'Engineering', 'slug' => 'engineering'])->withTranslation('ar', ['name' => 'الهندسة', 'slug' => 'الهندسة'])->create();
    $tag = Tag::factory()->withTranslation('en', ['name' => 'Laravel', 'slug' => 'laravel'])->withTranslation('ar', ['name' => 'لارافيل', 'slug' => 'لارافيل'])->create();
    $post = Post::factory()
        ->withTranslation('en', ['title' => 'Laravel vs Node for SaaS', 'slug' => 'laravel-vs-node', 'body' => '<p>Choosing a stack.</p><h2>When Laravel wins</h2><p>Admin-heavy SaaS.</p>', 'seo_description' => 'A practical comparison of Laravel and Node.js for SaaS products.'])
        ->withTranslation('ar', ['title' => 'لارافيل أم نود', 'slug' => 'لارافيل-أم-نود', 'body' => '<p>اختيار التقنية.</p><h2>متى يفوز لارافيل</h2>', 'seo_description' => 'مقارنة عملية بين لارافيل ونود لمنتجات SaaS.'])
        ->create(['post_category_id' => $category->id]);
    $post->tags()->attach($tag);

    $slugs = [
        'services.show' => ['web-development', 'تطوير-الويب'],
        'portfolio.show' => ['clinicflow', 'كلينك-فلو'],
        'products.show' => ['fanz-erp', 'فانز-erp'],
        'careers.show' => ['laravel-developer', 'مطور-لارافيل'],
        'blog.show' => ['laravel-vs-node', 'لارافيل-أم-نود'],
        'blog.category' => ['engineering', 'الهندسة'],
        'blog.tag' => ['laravel', 'لارافيل'],
        'pages.show' => ['privacy-policy', 'سياسة-الخصوصية'],
    ];

    return collect(publicSiteRouteNames())->mapWithKeys(fn (string $name): array => [$name => [
        'en' => route($name, ['locale' => 'en', ...(isset($slugs[$name]) ? ['slug' => $slugs[$name][0]] : [])], false),
        'ar' => route($name, ['locale' => 'ar', ...(isset($slugs[$name]) ? ['slug' => $slugs[$name][1]] : [])], false),
    ]])->all();
}

/**
 * Parses an HTML response body into an XPath, keeping UTF-8.
 */
function htmlXPath(string $html): DOMXPath
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}
