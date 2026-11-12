<?php

use App\Enums\KillSwitchKey;
use App\Enums\Role;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Enums\SocialVariantStatus;
use App\Jobs\PublishSocialPost;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Models\User;
use App\Services\KillSwitches;
use App\Services\Social\SocialDelivery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    $this->approver = User::factory()->role(Role::SocialMediaManager)->create();
});

/**
 * An approved post that is due now, with one variant per given account (status queued, as dispatchDue leaves it).
 *
 * @param  list<SocialAccount>  $accounts
 * @param  list<Media>  $media
 * @return array{0: SocialPost, 1: list<SocialPostVariant>}
 */
function duePost(User $reviewer, array $accounts, array $media = [], array $attributes = [], SocialVariantStatus $variantStatus = SocialVariantStatus::Queued): array
{
    $post = SocialPost::factory()->approved($reviewer)->create([
        'status' => $variantStatus === SocialVariantStatus::Pending ? SocialPostStatus::Scheduled : SocialPostStatus::Publishing,
        'scheduled_at' => now()->subMinute(),
        'title' => 'ClinicFlow case study',
        ...$attributes,
    ]);
    $post->syncMediaCollection(SocialPost::MEDIA_COLLECTION, array_map(fn (Media $item): int => $item->id, $media));

    $variants = array_map(fn (SocialAccount $account): SocialPostVariant => SocialPostVariant::factory()->forAccount($account)->create([
        'social_post_id' => $post->id,
        'text' => 'How we helped a clinic network (7 branches) go digital',
        'hashtags' => '#healthtech #laravel',
        'status' => $variantStatus,
    ]), $accounts);

    return [$post, $variants];
}

function storedImage(array $attributes = []): Media
{
    $media = Media::factory()->create(['alt' => ['en' => 'Clinic dashboard'], ...$attributes]);
    Storage::disk('local')->put($media->original_path, 'fake-image-bytes');

    return $media;
}

test('due approved posts are queued per variant; manual accounts become ready for export; unapproved posts are skipped', function () {
    Queue::fake();
    $linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create();
    $tiktok = SocialAccount::factory()->platform(SocialPlatform::Tiktok)->create();
    [$post, [$direct, $manual]] = duePost($this->approver, [$linkedin, $tiktok], variantStatus: SocialVariantStatus::Pending);

    $unapproved = SocialPost::factory()->create(['status' => SocialPostStatus::Scheduled, 'scheduled_at' => now()->subMinute()]);
    SocialPostVariant::factory()->forAccount($linkedin)->create(['social_post_id' => $unapproved->id]);
    [$future] = duePost($this->approver, [$linkedin], attributes: ['scheduled_at' => now()->addHour()], variantStatus: SocialVariantStatus::Pending);

    $this->artisan('social:publish-due')->assertSuccessful();

    Queue::assertPushedOn('social', PublishSocialPost::class, fn (PublishSocialPost $job): bool => $job->variantId === $direct->id);
    Queue::assertPushed(PublishSocialPost::class, 1);

    expect($post->refresh()->status)->toBe(SocialPostStatus::Publishing)
        ->and($direct->refresh()->status)->toBe(SocialVariantStatus::Queued)
        ->and($manual->refresh()->status)->toBe(SocialVariantStatus::ReadyForExport)
        ->and($unapproved->refresh()->status)->toBe(SocialPostStatus::Scheduled)
        ->and($future->refresh()->status)->toBe(SocialPostStatus::Scheduled)
        ->and(AuditLog::query()->where('operation', 'social_post.queued')->count())->toBe(1);
});

test('the social publishing kill switch stops the scheduler and the publish job', function () {
    Queue::fake();
    $linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create();
    [$scheduled] = duePost($this->approver, [$linkedin], variantStatus: SocialVariantStatus::Pending);
    [$publishing, [$queued]] = duePost($this->approver, [$linkedin]);

    app(KillSwitches::class)->set(KillSwitchKey::SocialPublishing, true, $this->approver);

    $this->artisan('social:publish-due')->assertSuccessful();
    Queue::assertNothingPushed();
    expect($scheduled->refresh()->status)->toBe(SocialPostStatus::Scheduled);

    expect(app(SocialDelivery::class)->deliver($queued->id))->toBeNull();
    Http::assertNothingSent();
    expect($queued->refresh()->status)->toBe(SocialVariantStatus::Pending)
        ->and($publishing->refresh()->status)->toBe(SocialPostStatus::Scheduled)
        ->and(AuditLog::query()->where('operation', 'social_post.publish_blocked')->exists())->toBeTrue();

    $this->actingAs($this->approver)->post(route('admin.social.posts.publish-now', $scheduled))->assertSessionHasErrors('status');
});

test('the AI automation master switch holds AI-origin posts only', function () {
    Queue::fake();
    $linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create();
    [$human] = duePost($this->approver, [$linkedin], variantStatus: SocialVariantStatus::Pending);
    [$ai] = duePost($this->approver, [$linkedin], attributes: ['origin' => 'ai'], variantStatus: SocialVariantStatus::Pending);

    app(KillSwitches::class)->set(KillSwitchKey::AiAutomation, true, $this->approver);
    $this->artisan('social:publish-due')->assertSuccessful();

    expect($human->refresh()->status)->toBe(SocialPostStatus::Publishing)
        ->and($ai->refresh()->status)->toBe(SocialPostStatus::Scheduled);
});

test('linkedin publishes through the posts API with an article link, hashtag markup and a first comment', function () {
    $linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create(['external_id' => '111']);
    [$post, [$variant]] = duePost($this->approver, [$linkedin], attributes: ['link_url' => 'https://codefanz.test/case', 'utm' => ['medium' => 'social']]);
    $variant->update(['first_comment' => 'Full story on our site']);

    Http::fake([
        'api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:7001']),
        'api.linkedin.com/rest/socialActions/*' => Http::response('', 201, ['x-restli-id' => 'urn:li:comment:1']),
    ]);

    expect(app(SocialDelivery::class)->deliver($variant->id))->toBeNull();

    Http::assertSent(function (Request $request) use ($linkedin): bool {
        if ($request->url() !== 'https://api.linkedin.com/rest/posts') {
            return false;
        }

        return $request['author'] === 'urn:li:organization:111'
            && $request['lifecycleState'] === 'PUBLISHED'
            && $request['visibility'] === 'PUBLIC'
            && $request['content']['article']['source'] === 'https://codefanz.test/case?utm_source=linkedin&utm_medium=social'
            && str_contains($request['commentary'], '\(7 branches\)')
            && str_contains($request['commentary'], '{hashtag|\#|healthtech}')
            && $request->hasHeader('Authorization', 'Bearer '.$linkedin->access_token)
            && $request->hasHeader('X-Restli-Protocol-Version', '2.0.0')
            && $request->hasHeader('LinkedIn-Version');
    });
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/rest/socialActions/'.rawurlencode('urn:li:share:7001').'/comments') && $request['message']['text'] === 'Full story on our site');

    $variant->refresh();
    expect($variant->status)->toBe(SocialVariantStatus::Published)
        ->and($variant->external_id)->toBe('urn:li:share:7001')
        ->and($variant->published_url)->toBe('https://www.linkedin.com/feed/update/urn:li:share:7001/')
        ->and($variant->attempts)->toBe(1)
        ->and($post->refresh()->status)->toBe(SocialPostStatus::Published)
        ->and(AuditLog::query()->where('operation', 'social_post.published')->exists())->toBeTrue();
});

test('facebook uploads photos unpublished from a signed URL and attaches them to one feed post', function () {
    $page = SocialAccount::factory()->connected(SocialPlatform::FacebookPage)->create(['external_id' => '5550001', 'access_token' => 'page-token']);
    $image = storedImage();
    [$post, [$variant]] = duePost($this->approver, [$page], [$image]);

    Http::fake([
        'graph.facebook.com/*/5550001/photos' => Http::response(['id' => 'photo-1']),
        'graph.facebook.com/*/5550001/feed' => Http::response(['id' => '5550001_9001']),
    ]);

    app(SocialDelivery::class)->deliver($variant->id);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/5550001/photos')
        && $request['published'] === 'false'
        && str_contains($request['url'], '/social-media/'.$image->uuid)
        && str_contains($request['url'], 'signature=')
        && $request['access_token'] === 'page-token');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/5550001/feed')
        && $request['attached_media[0]'] === json_encode(['media_fbid' => 'photo-1'])
        && str_contains($request['message'], '#healthtech'));

    expect($variant->refresh()->status)->toBe(SocialVariantStatus::Published)
        ->and($variant->published_url)->toBe('https://www.facebook.com/5550001_9001');
});

test('the signed media URL only serves images attached to a social post', function () {
    $image = storedImage();
    $other = storedImage();
    $post = SocialPost::factory()->create();
    $post->syncMediaCollection(SocialPost::MEDIA_COLLECTION, [$image->id]);

    $this->get(URL::temporarySignedRoute('social.media', now()->addHour(), ['media' => $image->uuid]))->assertOk();
    $this->get(URL::temporarySignedRoute('social.media', now()->addHour(), ['media' => $other->uuid]))->assertNotFound();
    $this->get(route('social.media', ['media' => $image->uuid]))->assertForbidden();
});

test('instagram creates a carousel container, publishes it and reuses the container when a retry is needed', function () {
    $instagram = SocialAccount::factory()->connected(SocialPlatform::InstagramBusiness)->create(['external_id' => '1784000', 'access_token' => 'page-token']);
    $images = [storedImage(), storedImage()];
    [, [$variant]] = duePost($this->approver, [$instagram], $images);

    Http::fake([
        'graph.facebook.com/*/1784000/media_publish' => Http::sequence()
            ->push(['error' => ['message' => 'Service temporarily unavailable', 'code' => 2, 'is_transient' => true]], 500)
            ->push(['id' => 'ig-post-1']),
        'graph.facebook.com/*/1784000/media' => Http::sequence()
            ->push(['id' => 'child-1'])
            ->push(['id' => 'child-2'])
            ->push(['id' => 'container-1']),
        'graph.facebook.com/*/ig-post-1*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/']),
    ]);

    expect(app(SocialDelivery::class)->deliver($variant->id, 1))->toBe(60);
    expect($variant->refresh()->status)->toBe(SocialVariantStatus::Queued)
        ->and($variant->platform_state['container'])->toBe('container-1');

    expect(app(SocialDelivery::class)->deliver($variant->id, 2))->toBeNull();

    $containerCalls = collect(Http::recorded())->filter(fn ($pair): bool => str_ends_with($pair[0]->url(), '/1784000/media'));
    expect($containerCalls)->toHaveCount(3);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/1784000/media') && ($request['media_type'] ?? null) === 'CAROUSEL' && $request['children'] === 'child-1,child-2');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/1784000/media') && ($request['is_carousel_item'] ?? null) === 'true' && $request['alt_text'] === 'Clinic dashboard');

    $variant->refresh();
    expect($variant->status)->toBe(SocialVariantStatus::Published)
        ->and($variant->external_id)->toBe('ig-post-1')
        ->and($variant->published_url)->toBe('https://www.instagram.com/p/abc/')
        ->and($variant->attempts)->toBe(2);
});

test('x uploads media, posts with the media ids and never posts twice across retries', function () {
    $x = SocialAccount::factory()->connected(SocialPlatform::X)->create(['handle' => 'codefanz', 'access_token' => 'x-token']);
    [$post, [$variant]] = duePost($this->approver, [$x], [storedImage()]);

    Http::fake([
        'api.x.com/2/media/upload' => Http::response(['data' => ['id' => 'media-77']]),
        'api.x.com/2/media/metadata' => Http::response(['data' => ['associated_metadata' => true]]),
        'api.x.com/2/tweets' => Http::sequence()
            ->push(['title' => 'Service Unavailable'], 503)
            ->push(['data' => ['id' => '1850000000000000001', 'text' => '...']], 201),
    ]);

    $delivery = app(SocialDelivery::class);
    expect($delivery->deliver($variant->id, 1))->toBe(60)
        ->and($variant->refresh()->status)->toBe(SocialVariantStatus::Queued);

    expect($delivery->deliver($variant->id, 2))->toBeNull()
        ->and($variant->refresh()->status)->toBe(SocialVariantStatus::Published);

    // The job running again (duplicate delivery) sends nothing.
    $delivery->deliver($variant->id, 3);

    $tweets = collect(Http::recorded())->filter(fn ($pair): bool => $pair[0]->url() === 'https://api.x.com/2/tweets');
    $uploads = collect(Http::recorded())->filter(fn ($pair): bool => $pair[0]->url() === 'https://api.x.com/2/media/upload');
    expect($tweets)->toHaveCount(2)->and($uploads)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/tweets'
        && $request['media']['media_ids'] === ['media-77']
        && str_contains($request['text'], '#healthtech #laravel')
        && $request->hasHeader('Authorization', 'Bearer x-token'));

    expect($variant->external_id)->toBe('1850000000000000001')
        ->and($variant->published_url)->toBe('https://x.com/codefanz/status/1850000000000000001')
        ->and($post->refresh()->status)->toBe(SocialPostStatus::Published);
});

test('a variant whose previous attempt ended without an answer is never re-sent automatically', function () {
    $x = SocialAccount::factory()->connected(SocialPlatform::X)->create();
    [$post, [$variant]] = duePost($this->approver, [$x], variantStatus: SocialVariantStatus::Publishing);

    app(SocialDelivery::class)->deliver($variant->id);

    Http::assertNothingSent();
    expect($variant->refresh()->status)->toBe(SocialVariantStatus::Failed)
        ->and($variant->last_error)->toContain('published twice')
        ->and($post->refresh()->status)->toBe(SocialPostStatus::Failed);
});

test('a rejected post fails permanently with the reason, and transient errors give up after the last attempt', function () {
    $x = SocialAccount::factory()->connected(SocialPlatform::X)->create();
    [$post, [$variant]] = duePost($this->approver, [$x]);

    Http::fake(['api.x.com/2/tweets' => Http::sequence()
        ->push(['detail' => 'You are not allowed to create a Tweet with duplicate content.', 'status' => 403], 403)
        ->push([], 503)]);
    expect(app(SocialDelivery::class)->deliver($variant->id))->toBeNull();

    $variant->refresh();
    expect($variant->status)->toBe(SocialVariantStatus::Failed)
        ->and($variant->last_error)->toContain('duplicate content')
        ->and($x->refresh()->status)->toBe(SocialAccountStatus::Connected)
        ->and($post->refresh()->status)->toBe(SocialPostStatus::Failed)
        ->and(AuditLog::query()->where('operation', 'social_post.publish_failed')->where('result', 'failure')->exists())->toBeTrue();

    [, [$second]] = duePost($this->approver, [$x]);
    expect(app(SocialDelivery::class)->deliver($second->id, 5, 5))->toBeNull()
        ->and($second->refresh()->status)->toBe(SocialVariantStatus::Failed)
        ->and($second->last_error)->toContain('5');

    // An approver can send a failed variant back to the queue.
    Queue::fake();
    $this->actingAs($this->approver)->post(route('admin.social.variants.retry', $second))->assertSessionHasNoErrors();
    expect($second->refresh()->status)->toBe(SocialVariantStatus::Queued);
    Queue::assertPushed(PublishSocialPost::class);
});

test('a revoked token or a missing app permission turns the variant into a manual export', function () {
    $linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create();
    $x = SocialAccount::factory()->connected(SocialPlatform::X)->create();
    [$post, [$li, $tw]] = duePost($this->approver, [$linkedin, $x]);

    Http::fake([
        'api.linkedin.com/*' => Http::response(['message' => 'Invalid access token'], 401),
        'api.x.com/2/tweets' => Http::response(['title' => 'Client Forbidden', 'detail' => 'This app does not have the right access level'], 403),
    ]);

    app(SocialDelivery::class)->deliver($li->id);
    app(SocialDelivery::class)->deliver($tw->id);

    expect($li->refresh()->status)->toBe(SocialVariantStatus::ReadyForExport)
        ->and($linkedin->refresh()->status)->toBe(SocialAccountStatus::TokenExpired)
        ->and($linkedin->access_token)->toBeNull()
        ->and($tw->refresh()->status)->toBe(SocialVariantStatus::ReadyForExport)
        ->and($x->refresh()->status)->toBe(SocialAccountStatus::RequiresAppApproval)
        ->and($post->refresh()->status)->toBe(SocialPostStatus::Publishing);
});

test('the publish job releases itself while the platform rate limit is used up', function () {
    $x = SocialAccount::factory()->connected(SocialPlatform::X)->create();
    [, [$variant]] = duePost($this->approver, [$x]);

    foreach (range(1, (int) config('codefanz.social.rate_limits.x')) as $hit) {
        RateLimiter::hit('social-publish:x', 60);
    }

    $job = (new PublishSocialPost($variant->id))->withFakeQueueInteractions();
    $job->handle(app(SocialDelivery::class));

    $job->assertReleased();
    Http::assertNothingSent();
    expect($variant->refresh()->status)->toBe(SocialVariantStatus::Queued)
        ->and($job->uniqueId())->toBe((string) $variant->id)
        ->and($job->queue)->toBe('social');
});

test('manual accounts get a copy-ready export and are marked posted with the live URL', function () {
    Queue::fake();
    $tiktok = SocialAccount::factory()->platform(SocialPlatform::Tiktok)->create(['name' => 'CodeFanz TikTok']);
    [$post, [$variant]] = duePost($this->approver, [$tiktok], [storedImage(['width' => 1080, 'height' => 1920])], variantStatus: SocialVariantStatus::Pending);

    $this->artisan('social:publish-due')->assertSuccessful();
    expect($variant->refresh()->status)->toBe(SocialVariantStatus::ReadyForExport);

    $this->actingAs($this->approver)->get(route('admin.social.posts.show', $post))
        ->assertOk()
        ->assertSee('data-manual-export', false)
        ->assertSee('data-copy="How we helped a clinic network (7 branches) go digital', false)
        ->assertSee(__('Download original'))
        ->assertSee(__('Mark as posted manually'));

    $this->actingAs($this->approver)->post(route('admin.social.variants.posted', $variant), ['published_url' => 'https://evil.example.com/post/1'])
        ->assertSessionHasErrors('published_url');

    $this->actingAs($this->approver)->post(route('admin.social.variants.posted', $variant), ['published_url' => 'https://www.tiktok.com/@codefanz/video/123'])
        ->assertSessionHasNoErrors();

    $variant->refresh();
    expect($variant->status)->toBe(SocialVariantStatus::ExportedManually)
        ->and($variant->published_url)->toBe('https://www.tiktok.com/@codefanz/video/123')
        ->and($variant->exported_by)->toBe($this->approver->id)
        ->and($post->refresh()->status)->toBe(SocialPostStatus::ExportedManually)
        ->and(AuditLog::query()->where('operation', 'social_post.exported_manually')->exists())->toBeTrue();
});

test('an unapproved post cannot be marked as posted manually', function () {
    $tiktok = SocialAccount::factory()->platform(SocialPlatform::Tiktok)->create();
    $post = SocialPost::factory()->create();
    $variant = SocialPostVariant::factory()->forAccount($tiktok)->create(['social_post_id' => $post->id, 'status' => SocialVariantStatus::ReadyForExport]);

    $this->actingAs($this->approver)->post(route('admin.social.variants.posted', $variant), ['published_url' => 'https://www.tiktok.com/@codefanz/video/1'])
        ->assertSessionHasErrors('status');

    expect($variant->refresh()->status)->toBe(SocialVariantStatus::ReadyForExport);
});
