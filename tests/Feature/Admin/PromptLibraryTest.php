<?php

use App\Enums\AiFeature;
use App\Enums\AiGenerationStatus;
use App\Enums\KillSwitchKey;
use App\Enums\PromptPurpose;
use App\Enums\Role;
use App\Models\AiGeneration;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\BrandProfile;
use App\Models\Post;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Ai\AiUsageLimits;
use App\Services\Ai\PromptTemplateValidator;
use App\Services\KillSwitches;
use App\Services\Settings;
use Database\Seeders\AiStudioSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
    $this->manager = User::factory()->role(Role::ContentManager)->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function promptPayload(array $overrides = []): array
{
    return [
        'key' => 'case-study',
        'name' => 'Case study',
        'purpose' => PromptPurpose::BlogDraft->value,
        'locale' => '',
        'description' => 'From a project record',
        'system_template' => "You write for {{brand_name}}.\n{{glossary}}",
        'user_template' => 'Write a case study about {{project}} for {{industry}}.',
        'variables' => [
            ['name' => 'project', 'label' => 'Project', 'required' => '1', 'max' => 300],
            ['name' => 'industry', 'label' => 'Industry', 'max' => 100],
            ['name' => '', 'label' => ''],
        ],
        'is_active' => '1',
        ...$overrides,
    ];
}

test('prompt managers create a template; version 1 is stored and the action is audited', function () {
    $this->actingAs($this->manager)->get(route('admin.prompts.create'))->assertOk();

    $this->actingAs($this->manager)->post(route('admin.prompts.store'), promptPayload())->assertRedirect();

    $template = PromptTemplate::query()->sole();
    expect($template->version)->toBe(1)
        ->and($template->declaredVariables())->toHaveCount(2)
        ->and($template->declaredVariables()[0])->toBe(['name' => 'project', 'label' => 'Project', 'required' => true, 'max' => 300])
        ->and($template->versions()->sole()->version)->toBe(1)
        ->and(AuditLog::query()->where('operation', 'prompt_template.created')->exists())->toBeTrue();

    $this->actingAs($this->manager)->get(route('admin.prompts.index'))->assertOk()->assertSee('case-study');
});

test('template variables are validated', function (array $overrides, string $field) {
    $this->actingAs($this->manager)->post(route('admin.prompts.store'), promptPayload($overrides))->assertSessionHasErrors($field);

    expect(PromptTemplate::query()->count())->toBe(0);
})->with([
    'undeclared variable in the user prompt' => [['user_template' => 'Write about {{project}}, {{industry}} and {{budget}}.'], 'user_template'],
    'declared variable never used' => [['user_template' => 'Write about {{project}}.'], 'user_template'],
    'staff variable in the system prompt' => [['system_template' => 'You write about {{project}}.'], 'system_template'],
    'invalid variable name' => [['variables' => [['name' => 'Project-Name'], ['name' => 'industry']], 'user_template' => '{{industry}}'], 'variables.0.name'],
    'reserved variable name' => [['variables' => [['name' => 'glossary']], 'user_template' => '{{glossary}}'], 'variables.0.name'],
    'duplicate variable' => [['variables' => [['name' => 'project'], ['name' => 'project']], 'user_template' => '{{project}}'], 'variables.1.name'],
    'bad key' => [['key' => 'Case Study!'], 'key'],
    'unknown purpose' => [['purpose' => 'press_release'], 'purpose'],
]);

test('changing the prompt text bumps the version and keeps history; a stale form is rejected', function () {
    $template = PromptTemplate::factory()->create(['key' => 'blog', 'version' => 1]);
    $template->versions()->create(['version' => 1, 'system_template' => $template->system_template, 'user_template' => $template->user_template, 'variables' => $template->variables]);

    $payload = promptPayload(['key' => 'blog', 'lock_version' => 1]);
    $this->actingAs($this->manager)->put(route('admin.prompts.update', $template), $payload)->assertRedirect(route('admin.prompts.edit', $template));

    expect($template->fresh()->version)->toBe(2)
        ->and($template->versions()->pluck('version')->all())->toBe([1, 2]);

    $this->actingAs($this->manager)->put(route('admin.prompts.update', $template), [...$payload, 'name' => 'Renamed', 'lock_version' => 2])->assertRedirect();
    expect($template->fresh()->version)->toBe(2)->and($template->fresh()->name)->toBe('Renamed');

    $this->actingAs($this->manager)->put(route('admin.prompts.update', $template), [...$payload, 'lock_version' => 1])->assertSessionHasErrors('lock_version');

    $this->actingAs($this->manager)->get(route('admin.prompts.edit', $template))->assertOk()->assertSee('data-version-history', false)->assertSee('v2');
});

test('a dry run calls the provider, is logged as usage and cannot be used', function () {
    $settings = app(Settings::class);
    $settings->set('integrations', 'ai_provider', 'openai');
    $settings->set('integrations', 'openai_api_key', 'test-key-openai', encrypted: true);
    $template = PromptTemplate::factory()->create();
    Http::fake(['api.openai.com/*' => Http::response([
        'id' => 'chatcmpl-dry', 'model' => 'gpt-4.1-mini',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode(['en' => ['title' => 'Dry title', 'excerpt' => 'E', 'body' => 'B', 'seo_title' => 'S', 'seo_description' => 'D']]), 'refusal' => null], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 80],
    ])]);

    $this->actingAs($this->manager)->post(route('admin.prompts.test', $template), ['inputs' => ['topic' => 'Testing'], 'test_locale' => 'en'])->assertRedirect();

    $generation = AiGeneration::query()->sole();
    $usage = AiUsageLog::query()->sole();
    expect($generation->is_dry_run)->toBeTrue()
        ->and($generation->status)->toBe(AiGenerationStatus::Generated)
        ->and($usage->feature)->toBe(AiFeature::ContentStudio)
        ->and($usage->user_id)->toBe($this->manager->id)
        ->and($usage->input_tokens)->toBe(120);

    $this->actingAs($this->manager)->get(route('admin.ai-studio.generations.show', $generation))->assertOk()->assertSee('data-dry-run', false)->assertDontSee('data-generation-actions', false);
    $this->actingAs($this->manager)->post(route('admin.ai-studio.generations.draft', $generation))->assertSessionHasErrors('generation');
    expect(Post::query()->count())->toBe(0);
});

test('only prompt managers reach the prompt library and the brand voice', function () {
    $template = PromptTemplate::factory()->create();

    foreach ([Role::SeoSpecialist, Role::SalesManager] as $role) {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user)->get(route('admin.prompts.index'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.prompts.update', $template), promptPayload())->assertForbidden();
        $this->actingAs($user)->post(route('admin.prompts.test', $template), ['test_locale' => 'en'])->assertForbidden();
        $this->actingAs($user)->get(route('admin.brand-voice.edit'))->assertForbidden();
    }
});

test('the brand voice is edited bilingually with lists, glossary pairs and domains, and audited', function () {
    $profile = BrandProfile::current();

    $this->actingAs($this->manager)->get(route('admin.brand-voice.edit'))->assertOk();

    $this->actingAs($this->manager)->put(route('admin.brand-voice.update'), [
        'name' => 'CodeFanz',
        'lock_version' => $profile->lock_version,
        'tone' => ['en' => 'Practical.', 'ar' => 'عملي.'],
        'audience' => ['en' => 'CTOs', 'ar' => 'المديرون التقنيون'],
        'do_rules_text' => ['en' => "Be concrete\nBe brief", 'ar' => 'كن محددًا'],
        'dont_rules_text' => ['en' => 'Invent numbers', 'ar' => ''],
        'banned_claims_text' => "guaranteed\nنضمن",
        'glossary_text' => "customer portal = بوابة العملاء\nmilestone = مرحلة",
        'allowed_domains_text' => 'Laravel.com',
    ])->assertRedirect(route('admin.brand-voice.edit'));

    $profile->refresh();
    expect($profile->rulesFor('do_rules', 'en'))->toBe(['Be concrete', 'Be brief'])
        ->and($profile->glossaryPairs())->toBe([['en' => 'customer portal', 'ar' => 'بوابة العملاء'], ['en' => 'milestone', 'ar' => 'مرحلة']])
        ->and($profile->bannedClaims())->toBe(['guaranteed', 'نضمن'])
        ->and($profile->allowedDomains())->toBe(['laravel.com'])
        ->and(AuditLog::query()->where('operation', 'brand_profile.updated')->exists())->toBeTrue();

    $this->actingAs($this->manager)->put(route('admin.brand-voice.update'), ['name' => 'X', 'lock_version' => $profile->lock_version, 'glossary_text' => 'no separator here'])
        ->assertSessionHasErrors('glossary_text');
});

test('the seeded prompt library is valid against the template rules', function () {
    $this->seed(AiStudioSeeder::class);
    $validator = app(PromptTemplateValidator::class);

    expect(PromptTemplate::query()->count())->toBe(count(PromptPurpose::cases()));

    PromptTemplate::query()->get()->each(fn (PromptTemplate $template) => expect($validator->errors($template->system_template, $template->user_template, $template->variables))->toBe([]));
});

test('the AI governance page lists every feature switch with usage and toggles them through the audited endpoint', function () {
    $admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();
    app(Settings::class)->set('ai', 'content_studio_monthly_tokens', '50000');

    $response = $this->actingAs($admin)->get(route('admin.ai-governance'))->assertOk();
    foreach (AiFeature::cases() as $feature) {
        $response->assertSee('data-feature="'.$feature->value.'"', false);
    }
    $response->assertSee('50,000')->assertSee('data-kill-switch="mcp_writes"', false);

    $this->actingAs($admin)->put(route('admin.emergency.update', KillSwitchKey::AiContentStudio->value), ['active' => 1])->assertRedirect();
    expect(app(KillSwitches::class)->isActive(KillSwitchKey::AiContentStudio))->toBeTrue();

    $this->actingAs($this->manager)->get(route('admin.ai-governance'))->assertForbidden();
});

test('usage limits are configured in settings with validation', function () {
    $admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();

    $this->actingAs($admin)->get(route('admin.settings.edit', 'ai'))->assertOk()->assertSee(__('AI requests per staff member per day'));
    $this->actingAs($admin)->put(route('admin.settings.update', 'ai'), ['settings' => ['user_daily_requests' => '-3']])->assertSessionHasErrors('settings.user_daily_requests');
    $this->actingAs($admin)->put(route('admin.settings.update', 'ai'), ['settings' => ['user_daily_requests' => '25', 'social_monthly_tokens' => '0']])->assertRedirect();

    expect(app(Settings::class)->get('ai', 'user_daily_requests'))->toBe('25')
        ->and(app(AiUsageLimits::class)->featureMonthlyTokenLimit(AiFeature::Social))->toBe(0);
});
