<?php

use App\Enums\PageSectionType;
use App\Enums\PostStatus;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;

/**
 * A published bilingual article with a category, a tag and an author profile.
 */
function publishedArticle(array $state = []): Post
{
    $author = User::factory()->create(['name' => 'Youssef Magdy', 'author_title' => ['en' => 'Lead Engineer', 'ar' => 'مهندس رئيسي'], 'author_bio' => ['en' => 'Builds bilingual SaaS products.']]);
    $category = PostCategory::factory()->withTranslation('en', ['name' => 'Engineering', 'slug' => 'engineering'])->withTranslation('ar', ['name' => 'الهندسة', 'slug' => 'الهندسة'])->create();
    $tag = Tag::factory()->withTranslation('en', ['name' => 'Laravel', 'slug' => 'laravel'])->withTranslation('ar', ['name' => 'لارافيل', 'slug' => 'لارافيل'])->create();

    $post = Post::factory()
        ->withTranslation('en', [
            'title' => 'Laravel vs Node for your next SaaS & beyond',
            'slug' => 'laravel-vs-node',
            'excerpt' => 'How we choose a backend <stack> with clients.',
            'body' => '<p>Choosing a backend stack shapes hiring.</p><h2>When Laravel wins</h2><p>Admin-heavy SaaS.</p><h3>Queues</h3><aside data-callout="tip"><p>Rule of thumb</p></aside><pre><code>php artisan queue:work</code></pre><h2>When Node wins</h2>',
            'seo_description' => 'A practical comparison of Laravel and Node.js for SaaS products.',
        ])
        ->withTranslation('ar', [
            'title' => 'لارافيل أم نود لمنتجك القادم',
            'slug' => 'لارافيل-أم-نود',
            'excerpt' => 'كيف نختار التقنية مع عملائنا.',
            'body' => '<p>اختيار التقنية يؤثر على التوظيف.</p><h2>متى يفوز لارافيل</h2>',
        ])
        ->create(['author_id' => $author->id, 'post_category_id' => $category->id, ...$state]);
    $post->tags()->attach($tag);

    return $post;
}

test('the blog index lists published articles with a featured article and category tabs', function () {
    $featured = publishedArticle(['is_featured' => true]);
    Post::factory()->withTranslation('en', ['title' => 'Core Web Vitals checklist'])->create(['published_at' => now()->subDays(3)]);
    Post::factory()->draft()->withTranslation('en', ['title' => 'Secret draft article'])->create();
    Post::factory()->withTranslation('en', ['title' => 'Future scheduled article'])->create(['status' => PostStatus::Scheduled, 'published_at' => null, 'scheduled_for' => now()->addDay()]);
    Post::factory()->withTranslation('en', ['title' => 'Published in the future'])->create(['published_at' => now()->addDay()]);

    $this->get('/en/blog')
        ->assertSuccessful()
        ->assertSee('data-featured-post', false)
        ->assertSee('Laravel vs Node for your next SaaS &amp; beyond', false)
        ->assertSee('Core Web Vitals checklist')
        ->assertSee('Engineering')
        ->assertDontSee('Secret draft article')
        ->assertDontSee('Future scheduled article')
        ->assertDontSee('Published in the future')
        ->assertSee('hreflang="ar" href="'.url('/ar/blog').'"', false)
        ->assertSee('application/rss+xml', false);

    $this->get('/en/blog?q=vitals')->assertSuccessful()->assertSee('Core Web Vitals checklist')->assertDontSee('data-featured-post', false)->assertSee('noindex', false);
    $this->get('/en/blog?q=nothing-matches')->assertSuccessful()->assertSee('No articles match your search');
});

test('the blog index renders in Arabic, right to left', function () {
    publishedArticle();

    $this->get('/ar/blog')
        ->assertSuccessful()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('لارافيل أم نود لمنتجك القادم')
        ->assertSee('الهندسة');
});

test('category and tag archives filter articles and link translated slugs', function () {
    publishedArticle();
    Post::factory()->withTranslation('en', ['title' => 'Uncategorised article'])->create();

    $this->get('/en/blog/category/engineering')
        ->assertSuccessful()
        ->assertSee('Laravel vs Node')
        ->assertDontSee('Uncategorised article')
        ->assertSee('hreflang="ar" href="'.url('/ar/blog/category/'.rawurlencode('الهندسة')).'"', false);

    $this->get('/ar/blog/tag/'.rawurlencode('لارافيل'))->assertSuccessful()->assertSee('لارافيل أم نود');
    $this->get('/en/blog/tag/laravel')->assertSuccessful()->assertDontSee('Uncategorised article');
    $this->get('/en/blog/category/missing')->assertNotFound();
});

test('an article page shows byline, table of contents, rich body, author bio and related articles', function () {
    $post = publishedArticle();
    Post::factory()->withTranslation('en', ['title' => 'Related engineering read'])->create(['post_category_id' => $post->post_category_id]);

    $response = $this->get('/en/blog/laravel-vs-node')
        ->assertSuccessful()
        ->assertSee('<h1 id="article-title"', false)
        ->assertSee('Youssef Magdy')
        ->assertSee('Lead Engineer')
        ->assertSee('Builds bilingual SaaS products.')
        ->assertSee('href="#when-laravel-wins"', false)
        ->assertSee('<h2 id="when-laravel-wins">', false)
        ->assertSee('href="#queues"', false)
        ->assertSee('<aside data-callout="tip" role="note">', false)
        ->assertSee('<pre><code>php artisan queue:work</code></pre>', false)
        ->assertSee('data-share-link="linkedin"', false)
        ->assertSee('Keep reading')
        ->assertSee('Related engineering read')
        ->assertSee('#Laravel')
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<link rel="alternate" hreflang="ar" href="'.url('/ar/blog/'.rawurlencode('لارافيل-أم-نود')).'">', false)
        ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/en/blog/laravel-vs-node').'">', false)
        ->assertSee('<link rel="canonical" href="'.url('/en/blog/laravel-vs-node').'">', false)
        ->assertDontSee('<script src=', false);

    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $schemas = collect($matches[1])->map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR))->keyBy('@type');

    expect($schemas['BlogPosting'])
        ->toMatchArray(['headline' => 'Laravel vs Node for your next SaaS & beyond', 'inLanguage' => 'en', 'url' => url('/en/blog/laravel-vs-node')])
        ->and($schemas['BlogPosting']['author'])->toBe(['@type' => 'Person', 'name' => 'Youssef Magdy', 'jobTitle' => 'Lead Engineer'])
        ->and($schemas['BlogPosting']['datePublished'])->toBe($post->published_at->toIso8601String())
        ->and($schemas['BreadcrumbList']['itemListElement'])->toHaveCount(4)
        ->and($schemas['BreadcrumbList']['itemListElement'][3]['item'])->toBe(url('/en/blog/laravel-vs-node'));
});

test('an Arabic article renders right to left with its own translation', function () {
    publishedArticle();

    $this->get('/ar/blog/'.rawurlencode('لارافيل-أم-نود'))
        ->assertSuccessful()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('لارافيل أم نود لمنتجك القادم')
        ->assertSee('مهندس رئيسي')
        ->assertSee('href="#'.'متى-يفوز-لارافيل'.'"', false)
        ->assertSee('"inLanguage":"ar"', false);
});

test('a canonical url overrides the default canonical link', function () {
    publishedArticle();
    Post::query()->first()->translations()->where('locale', 'en')->update(['canonical_url' => 'https://medium.com/@codefanz/laravel-vs-node']);

    $this->get('/en/blog/laravel-vs-node')->assertSee('<link rel="canonical" href="https://medium.com/@codefanz/laravel-vs-node">', false);
});

test('drafts, scheduled, future, archived and deleted articles return 404', function (array $state) {
    publishedArticle($state);

    $this->get('/en/blog/laravel-vs-node')->assertNotFound();
})->with([
    'draft' => [['status' => PostStatus::Draft, 'published_at' => null]],
    'in review' => [['status' => PostStatus::InReview, 'published_at' => null]],
    'approved' => [['status' => PostStatus::Approved, 'published_at' => null]],
    'scheduled' => [['status' => PostStatus::Scheduled, 'published_at' => null, 'scheduled_for' => now()->addHour()]],
    'published in the future' => [['published_at' => now()->addHour()]],
    'archived' => [['status' => PostStatus::Archived]],
    'deleted' => [['deleted_at' => now()]],
]);

test('an article missing in the requested language returns 404', function () {
    Post::factory()->withTranslation('en', ['slug' => 'english-only'])->create(['is_single_language' => true]);

    $this->get('/en/blog/english-only')->assertSuccessful()->assertDontSee('<link rel="alternate" hreflang="ar"', false);
    $this->get('/ar/blog/english-only')->assertNotFound();
});

test('the RSS feed is well-formed, escaped and per locale', function () {
    publishedArticle();
    Post::factory()->draft()->withTranslation('en', ['title' => 'Secret draft article'])->create();

    $response = $this->get('/en/blog/feed')->assertSuccessful();

    expect($response->headers->get('Content-Type'))->toContain('application/rss+xml');

    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse()
        ->and((string) $xml->channel->language)->toBe('en')
        ->and($xml->channel->item)->toHaveCount(1)
        ->and((string) $xml->channel->item[0]->title)->toBe('Laravel vs Node for your next SaaS & beyond')
        ->and((string) $xml->channel->item[0]->description)->toBe('How we choose a backend <stack> with clients.')
        ->and((string) $xml->channel->item[0]->link)->toBe(url('/en/blog/laravel-vs-node'))
        ->and((string) $xml->channel->item[0]->children('content', true)->encoded)->toContain('<h2 id="when-laravel-wins">')
        ->and($response->getContent())->not->toContain('Secret draft article');

    $arabic = simplexml_load_string($this->get('/ar/blog/feed')->assertSuccessful()->getContent());
    expect((string) $arabic->channel->language)->toBe('ar')
        ->and((string) $arabic->channel->item[0]->title)->toBe('لارافيل أم نود لمنتجك القادم');
});

test('the homepage latest articles section renders published articles', function () {
    PageSection::ensureHomeSections();
    $this->get('/en')->assertDontSee('data-section="latest_articles"', false);

    publishedArticle();
    Post::factory()->draft()->withTranslation('en', ['title' => 'Secret draft article'])->create();

    $this->get('/en')
        ->assertSuccessful()
        ->assertSee('data-section="latest_articles"', false)
        ->assertSee('Latest from our blog')
        ->assertSee('Laravel vs Node')
        ->assertDontSee('Secret draft article');

    PageSection::query()->home()->where('type', PageSectionType::LatestArticles)->update(['is_visible' => false]);
    $this->get('/en')->assertDontSee('data-section="latest_articles"', false);
});

test('the blog appears in the default navigation and can be linked from menus', function () {
    $this->get('/en')->assertSee('href="'.url('/en/blog').'"', false);
});
