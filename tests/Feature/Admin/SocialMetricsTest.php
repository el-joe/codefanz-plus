<?php

use App\Enums\Role;
use App\Enums\SocialPlatform;
use App\Enums\SocialVariantStatus;
use App\Jobs\FetchSocialMetrics;
use App\Models\SocialAccount;
use App\Models\SocialMetricSnapshot;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Models\User;
use App\Services\Social\SocialMetrics;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->manager = User::factory()->role(Role::SocialMediaManager)->create();
});

function publishedVariant(SocialAccount $account, array $attributes = []): SocialPostVariant
{
    $post = SocialPost::factory()->approved()->create(['status' => 'published', 'title' => 'Post on '.$account->platform->shortLabel()]);

    return SocialPostVariant::factory()->forAccount($account)->create([
        'social_post_id' => $post->id,
        'status' => SocialVariantStatus::Published,
        'external_id' => '1850000000000000001',
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
}

test('metrics are pulled for API posts and stored as snapshots without inventing missing values', function () {
    $x = SocialAccount::factory()->connected(SocialPlatform::X)->create();
    $instagram = SocialAccount::factory()->connected(SocialPlatform::InstagramBusiness)->create(['external_id' => '1784000']);
    $tweet = publishedVariant($x);
    $reel = publishedVariant($instagram, ['external_id' => 'ig-post-1']);
    $old = publishedVariant($x, ['external_id' => '1', 'published_at' => now()->subDays(200)]);

    Http::fake([
        'api.x.com/2/tweets/*' => Http::response(['data' => ['id' => '1850000000000000001', 'public_metrics' => ['impression_count' => 1200, 'like_count' => 30, 'reply_count' => 4, 'retweet_count' => 5, 'quote_count' => 1]]]),
        'graph.facebook.com/*/ig-post-1/insights*' => Http::response(['data' => [
            ['name' => 'views', 'values' => [['value' => 900]]],
            ['name' => 'total_interactions', 'values' => [['value' => 75]]],
        ]]),
    ]);

    Queue::fake();
    $this->artisan('social:fetch-metrics')->assertSuccessful();
    Queue::assertPushedOn('social', FetchSocialMetrics::class);

    (new FetchSocialMetrics)->handle(app(SocialMetrics::class));

    $tweetSnapshot = SocialMetricSnapshot::query()->where('social_post_variant_id', $tweet->id)->sole();
    $reelSnapshot = SocialMetricSnapshot::query()->where('social_post_variant_id', $reel->id)->sole();

    expect($tweetSnapshot->impressions)->toBe(1200)
        ->and($tweetSnapshot->engagements)->toBe(40)
        ->and($tweetSnapshot->clicks)->toBeNull()
        ->and($reelSnapshot->impressions)->toBe(900)
        ->and($reelSnapshot->engagements)->toBe(75)
        ->and($reelSnapshot->clicks)->toBeNull()
        ->and(SocialMetricSnapshot::query()->where('social_post_variant_id', $old->id)->exists())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'tweet.fields=public_metrics'));
});

test('the dashboard shows honest states: numbers only from the platform, never for manual posts or unsupported platforms', function () {
    $linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create(['name' => 'CodeFanz LinkedIn']);
    $tiktok = SocialAccount::factory()->platform(SocialPlatform::Tiktok)->create(['name' => 'CodeFanz TikTok']);
    $x = SocialAccount::factory()->connected(SocialPlatform::X)->create(['name' => 'CodeFanz X']);

    $reported = publishedVariant($linkedin, ['external_id' => 'urn:li:share:1']);
    $reported->snapshots()->create(['impressions' => 4321, 'engagements' => 87, 'clicks' => 19, 'captured_at' => now()]);
    $manual = publishedVariant($tiktok, ['status' => SocialVariantStatus::ExportedManually, 'external_id' => null, 'published_url' => 'https://www.tiktok.com/@codefanz/video/1', 'exported_at' => now()]);
    $failing = publishedVariant($x, ['metrics_error' => 'X metrics (HTTP 403): Unsupported Authentication']);
    $pending = publishedVariant($x, ['external_id' => '2']);

    $metrics = app(SocialMetrics::class);
    expect($metrics->stateOf($reported->load('latestSnapshot')))->toBe(SocialMetrics::AVAILABLE)
        ->and($metrics->stateOf($manual->load('latestSnapshot')))->toBe(SocialMetrics::MANUAL)
        ->and($metrics->stateOf($failing->load('latestSnapshot')))->toBe(SocialMetrics::ERROR)
        ->and($metrics->stateOf($pending->load('latestSnapshot')))->toBe(SocialMetrics::PENDING);

    $this->actingAs($this->manager)->get(route('admin.social.metrics.index'))
        ->assertOk()
        ->assertSee('4,321')
        ->assertSee('data-metrics-row="manual"', false)
        ->assertSee(__('Not available for manual posts'))
        ->assertSee('data-metrics-row="error"', false)
        ->assertSee('Unsupported Authentication')
        ->assertSee(__('Not collected yet'))
        ->assertSee('data-analytics-chart="social-chart-data"', false);

    $tiktokMeta = SocialPlatform::Tiktok;
    expect($tiktokMeta->supportsMetrics())->toBeFalse();
});

test('with no platform data the dashboard shows an empty state instead of zeros', function () {
    $this->actingAs($this->manager)->get(route('admin.social.metrics.index'))
        ->assertOk()
        ->assertSee(__('No platform data yet'))
        ->assertDontSee('data-analytics-chart', false);

    $this->manager->forceFill(['locale' => 'ar'])->save();
    $this->actingAs($this->manager)->get(route('admin.social.metrics.index'))->assertOk()->assertSee('dir="rtl"', false)->assertSee('مقاييس التواصل الاجتماعي');
});

test('a metrics error is stored on the variant and shown instead of numbers', function () {
    $linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create();
    $variant = publishedVariant($linkedin, ['external_id' => 'urn:li:share:9']);

    Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'Not enough permissions'], 403)]);
    app(SocialMetrics::class)->fetch();

    expect($variant->refresh()->metrics_error)->toContain('HTTP 403')
        ->and(SocialMetricSnapshot::query()->count())->toBe(0);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'organizationalEntityShareStatistics') && str_contains($request->url(), 'shares=List('.rawurlencode('urn:li:share:9').')'));
});
