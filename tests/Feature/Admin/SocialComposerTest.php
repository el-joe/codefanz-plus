<?php

use App\Enums\AiFeature;
use App\Enums\AiGenerationStatus;
use App\Enums\KillSwitchKey;
use App\Enums\Permission;
use App\Enums\PostOrigin;
use App\Enums\PromptPurpose;
use App\Enums\Role;
use App\Enums\SocialPlatform;
use App\Enums\SocialPostStatus;
use App\Models\AiGeneration;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\BrandProfile;
use App\Models\Media;
use App\Models\PromptTemplate;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostVariant;
use App\Models\User;
use App\Notifications\SocialPostSubmittedForReview;
use App\Services\KillSwitches;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
    $this->writer = User::factory()->create(['timezone' => 'Africa/Cairo']);
    $this->writer->givePermissionTo([Permission::AdminAccess->value, Permission::SocialManage->value, Permission::AiUse->value]);
    $this->approver = User::factory()->role(Role::SocialMediaManager)->create(['timezone' => 'Africa/Cairo']);
    $this->linkedin = SocialAccount::factory()->connected(SocialPlatform::LinkedinPage)->create(['name' => 'CodeFanz']);
    $this->x = SocialAccount::factory()->connected(SocialPlatform::X)->create(['name' => 'codefanz']);
});

/**
 * @param  array<int, array<string, mixed>>  $variants
 * @return array<string, mixed>
 */
function composerPayload(array $variants, array $overrides = []): array
{
    return [
        'title' => 'ClinicFlow case study',
        'locale' => 'en',
        'campaign' => 'Case studies Q4',
        'link_url' => 'https://codefanz.test/en/portfolio/clinicflow',
        'utm' => ['medium' => 'social', 'campaign' => 'q4-case-studies'],
        'variants' => $variants,
        ...$overrides,
    ];
}

function approvedSocialPost(User $reviewer, SocialAccount $account, array $attributes = []): SocialPost
{
    $post = SocialPost::factory()->approved($reviewer)->create($attributes);
    SocialPostVariant::factory()->forAccount($account)->create(['social_post_id' => $post->id, 'text' => 'How we helped a clinic network go digital.']);

    return $post;
}

test('the composer saves a draft with one variant per chosen account, tracked links and normalised hashtags', function () {
    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $this->linkedin->id => ['enabled' => '1', 'text' => 'How we helped a 7-branch clinic network replace phone bookings.', 'hashtags' => 'healthtech, #laravel', 'first_comment' => 'Read the case study', 'include_media' => '1'],
        $this->x->id => ['enabled' => '0', 'text' => 'ignored'],
    ], ['planned_for' => '2026-12-01T10:00']))->assertRedirect();

    $post = SocialPost::query()->sole();
    $variant = $post->variants()->sole();

    expect($post->status)->toBe(SocialPostStatus::Draft)
        ->and($post->author_id)->toBe($this->writer->id)
        ->and($post->planned_at->utc()->format('Y-m-d H:i'))->toBe('2026-12-01 08:00')
        ->and($variant->social_account_id)->toBe($this->linkedin->id)
        ->and($variant->hashtags)->toBe('#healthtech #laravel')
        ->and($variant->idempotency_key)->not->toBeEmpty()
        ->and($post->trackedLink('linkedin'))->toBe('https://codefanz.test/en/portfolio/clinicflow?utm_source=linkedin&utm_medium=social&utm_campaign=q4-case-studies')
        ->and(AuditLog::query()->where('operation', 'social_post.created')->exists())->toBeTrue();
});

test('character limits are enforced per platform, counting hashtags and links the way the platform does', function () {
    // X: 280 characters, every link counts as 23.
    $text = str_repeat('a', 250);

    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $this->x->id => ['enabled' => '1', 'text' => $text, 'hashtags' => '#one'],
    ]))->assertSessionHasErrors('variants.'.$this->x->id.'.text');

    // 250 + 2 + 4 (#one) + 2 + 23 (link) = 281 > 280; dropping the hashtag fits.
    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $this->x->id => ['enabled' => '1', 'text' => $text],
    ]))->assertSessionHasNoErrors();

    // The same text is fine on LinkedIn (3,000).
    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $this->linkedin->id => ['enabled' => '1', 'text' => str_repeat('b', 2900)],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $this->linkedin->id => ['enabled' => '1', 'text' => str_repeat('b', 3001)],
    ]))->assertSessionHasErrors('variants.'.$this->linkedin->id.'.text');

    expect(SocialPost::query()->count())->toBe(2);
});

test('platform media rules apply: instagram needs a jpeg between 4:5 and 1.91:1, x at most four images', function () {
    $instagram = SocialAccount::factory()->connected(SocialPlatform::InstagramBusiness)->create();
    $wide = Media::factory()->create(['width' => 3000, 'height' => 1000, 'mime_type' => 'image/jpeg']);
    $images = Media::factory()->count(5)->create();

    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $instagram->id => ['enabled' => '1', 'text' => 'Carousel', 'include_media' => '1'],
    ], ['media_ids' => [$wide->id]]))->assertSessionHasErrors('variants.'.$instagram->id.'.include_media');

    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $this->x->id => ['enabled' => '1', 'text' => 'Five images', 'include_media' => '1'],
    ], ['media_ids' => $images->pluck('id')->all()]))->assertSessionHasErrors('variants.'.$this->x->id.'.include_media');

    // Without images an Instagram draft can be saved, but not submitted.
    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $instagram->id => ['enabled' => '1', 'text' => 'Needs an image', 'include_media' => '1'],
    ]))->assertSessionHasNoErrors();

    $post = SocialPost::query()->sole();
    $this->actingAs($this->writer)->post(route('admin.social.posts.submit', $post))->assertSessionHasErrors('variants.'.$instagram->id.'.include_media');
    expect($post->refresh()->status)->toBe(SocialPostStatus::Draft);
});

test('submitting moves the post to review and notifies reviewers; empty text cannot be submitted', function () {
    Notification::fake();
    $post = SocialPost::factory()->create(['author_id' => $this->writer->id]);
    $variant = SocialPostVariant::factory()->forAccount($this->linkedin)->create(['social_post_id' => $post->id, 'text' => '']);

    $this->actingAs($this->writer)->post(route('admin.social.posts.submit', $post))->assertSessionHasErrors('variants.'.$this->linkedin->id.'.text');

    $variant->update(['text' => 'Ready for review']);
    $this->actingAs($this->writer)->post(route('admin.social.posts.submit', $post))->assertSessionHasNoErrors();

    expect($post->refresh()->status)->toBe(SocialPostStatus::InReview);
    Notification::assertSentTo($this->approver, SocialPostSubmittedForReview::class);
    Notification::assertNotSentTo($this->writer, SocialPostSubmittedForReview::class);
});

test('approval is required before scheduling: writers get 403 and unapproved posts keep their state', function () {
    $post = SocialPost::factory()->create(['author_id' => $this->writer->id]);
    SocialPostVariant::factory()->forAccount($this->linkedin)->create(['social_post_id' => $post->id]);

    $this->actingAs($this->writer)->post(route('admin.social.posts.schedule', $post), ['scheduled_for' => '2030-01-01T10:00'])->assertForbidden();
    $this->actingAs($this->writer)->post(route('admin.social.posts.approve', $post))->assertForbidden();
    $this->actingAs($this->writer)->post(route('admin.social.posts.publish-now', $post))->assertForbidden();

    $this->actingAs($this->approver)->post(route('admin.social.posts.schedule', $post), ['scheduled_for' => '2030-01-01T10:00'])->assertSessionHasErrors('status');
    $this->actingAs($this->approver)->post(route('admin.social.posts.publish-now', $post))->assertSessionHasErrors('status');

    expect($post->refresh()->status)->toBe(SocialPostStatus::Draft)->and($post->scheduled_at)->toBeNull();

    // Once approved, the approver can schedule it (time read in their time zone).
    $post->update(['status' => SocialPostStatus::InReview]);
    $this->actingAs($this->approver)->post(route('admin.social.posts.approve', $post), ['scheduled_for' => '2030-01-01T10:00'])->assertSessionHasNoErrors();

    $post->refresh();
    expect($post->status)->toBe(SocialPostStatus::Scheduled)
        ->and($post->reviewed_by)->toBe($this->approver->id)
        ->and($post->scheduled_at->utc()->format('Y-m-d H:i'))->toBe('2030-01-01 08:00');
});

test('the person who generated an AI post cannot approve or schedule it, even as super admin', function () {
    $post = SocialPost::factory()->inReview()->aiOrigin($this->approver)->create(['author_id' => $this->approver->id]);
    SocialPostVariant::factory()->forAccount($this->linkedin)->create(['social_post_id' => $post->id]);

    $this->actingAs($this->approver)->post(route('admin.social.posts.approve', $post))->assertForbidden();
    expect($post->refresh()->status)->toBe(SocialPostStatus::InReview);

    $this->actingAs($this->approver)->get(route('admin.social.posts.show', $post))->assertOk()->assertSee('data-own-ai-post', false);

    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();
    $post->update(['generated_by' => $admin->id]);
    $this->actingAs($admin)->post(route('admin.social.posts.approve', $post))->assertForbidden();

    $other = User::factory()->role(Role::SocialMediaManager)->create();
    $this->actingAs($other)->post(route('admin.social.posts.approve', $post))->assertSessionHasNoErrors();
    expect($post->refresh()->status)->toBe(SocialPostStatus::Approved);

    $this->actingAs($admin)->post(route('admin.social.posts.schedule', $post), ['scheduled_for' => '2030-01-01T10:00'])->assertForbidden();
});

test('editing an approved post sends it back to draft', function () {
    $post = approvedSocialPost($this->approver, $this->linkedin, ['status' => SocialPostStatus::Scheduled, 'scheduled_at' => now()->addDay()]);

    $this->actingAs($this->writer)->put(route('admin.social.posts.update', $post), composerPayload([
        $this->linkedin->id => ['enabled' => '1', 'text' => 'A different claim'],
    ], ['lock_version' => $post->lock_version]))->assertRedirect();

    $post->refresh();
    expect($post->status)->toBe(SocialPostStatus::Draft)
        ->and($post->approved_at)->toBeNull()
        ->and($post->scheduled_at)->toBeNull();

    // A stale form is refused.
    $this->actingAs($this->writer)->put(route('admin.social.posts.update', $post), composerPayload([
        $this->linkedin->id => ['enabled' => '1', 'text' => 'Stale'],
    ], ['lock_version' => 1]))->assertSessionHasErrors('lock_version');
});

test('generate with AI fills every variant from the social_post template through the gateway as an AI draft', function () {
    BrandProfile::factory()->create();
    PromptTemplate::factory()->create(['key' => 'social-post', 'purpose' => PromptPurpose::SocialPost, 'user_template' => 'Post for {{platform}} about {{topic}} {{link}}', 'variables' => [
        ['name' => 'platform', 'label' => 'Platform', 'required' => true, 'max' => 60],
        ['name' => 'topic', 'label' => 'Topic', 'required' => true, 'max' => 1000],
        ['name' => 'link', 'label' => 'Link', 'required' => false, 'max' => 300],
    ]]);
    $settings = app(Settings::class);
    $settings->set('integrations', 'ai_provider', 'openai');
    $settings->set('integrations', 'openai_api_key', 'test-key', encrypted: true);
    $settings->set('integrations', 'ai_monthly_budget', '100');

    Http::fake(['api.openai.com/*' => Http::response([
        'id' => 'chatcmpl-social', 'model' => 'gpt-4.1-mini',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode(['en' => ['text' => 'ClinicFlow cut no-shows.', 'hashtags' => '#healthtech #laravel', 'call_to_action' => 'Read the case study']]), 'refusal' => null], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 80],
    ])]);

    $this->actingAs($this->writer)->post(route('admin.social.posts.store'), composerPayload([
        $this->linkedin->id => ['enabled' => '1', 'text' => ''],
        $this->x->id => ['enabled' => '1', 'text' => ''],
    ], ['ai' => '1', 'topic' => 'ClinicFlow case study']))->assertRedirect();

    $post = SocialPost::query()->with('variants')->sole();

    expect($post->origin)->toBe(PostOrigin::Ai)
        ->and($post->generated_by)->toBe($this->writer->id)
        ->and($post->status)->toBe(SocialPostStatus::Draft)
        ->and($post->variants)->toHaveCount(2)
        ->and($post->variants->first()->text)->toBe("ClinicFlow cut no-shows.\n\nRead the case study")
        ->and($post->variants->first()->hashtags)->toBe('#healthtech #laravel')
        ->and($post->variants->first()->ai_generation_id)->not->toBeNull()
        ->and(AiGeneration::query()->where('status', AiGenerationStatus::SocialDraftCreated)->count())->toBe(2)
        ->and(AiUsageLog::query()->where('feature', AiFeature::Social)->count())->toBe(2);

    // The social AI kill switch stops generation before any provider call.
    app(KillSwitches::class)->set(KillSwitchKey::AiSocial, true, $this->approver);
    $this->actingAs($this->writer)->put(route('admin.social.posts.update', $post), composerPayload([
        $this->linkedin->id => ['enabled' => '1', 'text' => 'x'],
    ], ['ai' => '1', 'topic' => 'Again', 'lock_version' => $post->refresh()->lock_version]))->assertSessionHasErrors('ai');
    Http::assertSentCount(2);
});

test('the calendar shows posts by day and reschedules approved posts in the user time zone', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-13 09:00', 'UTC'));
    $approved = approvedSocialPost($this->approver, $this->linkedin, ['title' => 'Approved launch', 'status' => SocialPostStatus::Scheduled, 'scheduled_at' => CarbonImmutable::parse('2026-11-20 08:00', 'UTC')]);
    $draft = SocialPost::factory()->create(['title' => 'Draft idea', 'planned_at' => CarbonImmutable::parse('2026-11-18 08:00', 'UTC')]);

    $this->actingAs($this->approver)->get(route('admin.social.calendar', ['date' => '2026-11-13']))
        ->assertOk()
        ->assertSee('Approved launch')
        ->assertSee('Draft idea')
        ->assertSee('data-reschedule-url="'.route('admin.social.posts.reschedule', $approved).'"', false)
        ->assertDontSee('data-reschedule-url="'.route('admin.social.posts.reschedule', $draft).'"', false)
        ->assertSee(__('Scheduled ≠ approved'));

    $this->actingAs($this->approver)->get(route('admin.social.calendar', ['view' => 'week', 'date' => '2026-11-20']))->assertOk()->assertSee('Approved launch');

    $this->actingAs($this->approver)->postJson(route('admin.social.posts.reschedule', $approved), ['scheduled_for' => '2026-11-25T10:00'])
        ->assertOk()
        ->assertJsonPath('post.local_date', '2026-11-25')
        ->assertJsonPath('post.local_time', '10:00');
    expect($approved->refresh()->scheduled_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-25 08:00');

    $this->actingAs($this->approver)->postJson(route('admin.social.posts.reschedule', $draft), ['scheduled_for' => '2026-11-25T10:00'])->assertUnprocessable();
    $this->actingAs($this->approver)->postJson(route('admin.social.posts.reschedule', $approved), ['scheduled_for' => '2026-11-01T10:00'])->assertUnprocessable();
    $this->actingAs($this->writer)->postJson(route('admin.social.posts.reschedule', $approved), ['scheduled_for' => '2026-11-26T10:00'])->assertForbidden();

    expect($approved->refresh()->scheduled_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-25 08:00')
        ->and($draft->refresh()->scheduled_at)->toBeNull();

    CarbonImmutable::setTestNow();
});

test('social admin screens render in english and in arabic right-to-left', function () {
    $post = approvedSocialPost($this->approver, $this->linkedin, ['title' => 'Bilingual post']);

    foreach (['en', 'ar'] as $locale) {
        $this->approver->forceFill(['locale' => $locale])->save();
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';

        foreach ([route('admin.social.posts.index'), route('admin.social.posts.create'), route('admin.social.posts.show', $post), route('admin.social.posts.edit', $post), route('admin.social.calendar'), route('admin.social.metrics.index')] as $url) {
            $this->actingAs($this->approver)->get($url)->assertOk()->assertSee('dir="'.$dir.'"', false);
        }
    }

    $this->actingAs($this->approver)->get(route('admin.social.posts.create'))->assertSee('منشور جديد على وسائل التواصل');
    $this->actingAs($this->approver)->get(route('admin.social.calendar'))->assertSee('الجدولة ≠ الموافقة');
});
