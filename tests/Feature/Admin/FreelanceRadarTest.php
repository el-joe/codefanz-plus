<?php

use App\Actions\Radar\IngestRadarProjects;
use App\Actions\Radar\ScoreRadarProject;
use App\Actions\Radar\ScoreRadarProjectWithAi;
use App\Enums\AiUnavailableReason;
use App\Enums\GuardrailFlagType;
use App\Enums\KillSwitchKey;
use App\Enums\LeadSource;
use App\Enums\RadarAlertLevel;
use App\Enums\RadarBudgetType;
use App\Enums\RadarPlatformStatus;
use App\Enums\RadarProjectStatus;
use App\Enums\Role;
use App\Exceptions\AiUnavailable;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\RadarAlert;
use App\Models\RadarEvaluation;
use App\Models\RadarFilter;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use App\Models\Technology;
use App\Models\User;
use App\Services\KillSwitches;
use App\Services\Radar\Connectors\FreelancerConnector;
use App\Services\Radar\Connectors\UpworkConnector;
use App\Services\Radar\Parsers\MostaqlAlertParser;
use App\Services\Radar\Parsers\PeoplePerHourAlertParser;
use App\Services\Radar\RadarAlerts;
use App\Services\Radar\RadarFilterMatcher;
use App\Services\Radar\RadarPlatforms;
use App\Services\Radar\RadarProjectData;
use App\Services\Radar\RadarScorer;
use App\Services\Radar\RadarSettings;
use App\Services\Radar\TelegramNotifier;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Database\Seeders\AiStudioSeeder;
use Database\Seeders\RadarPlatformSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed(RadarPlatformSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));

    foreach (['Laravel', 'PHP', 'MySQL', 'Flutter', 'Vue'] as $name) {
        Technology::factory()->withTranslation('en', ['name' => $name])->create();
    }

    $this->sales = User::factory()->role(Role::SalesManager)->create(['name' => 'Mona Adel']);
    $this->freelancer = RadarPlatform::query()->where('key', RadarPlatform::FREELANCER)->sole();
});

function radarFixture(string $name): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/radar/'.$name));
}

function configureTelegram(): void
{
    $settings = app(Settings::class);
    $settings->set('radar', 'telegram_bot_token', '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsawQ', encrypted: true);
    $settings->set('radar', 'telegram_chat_id', '-1001234567890');
}

function radarConfigureAi(): void
{
    $settings = app(Settings::class);
    $settings->set('integrations', 'ai_provider', 'openai');
    $settings->set('integrations', 'openai_api_key', 'test-key-openai', encrypted: true);
}

/**
 * @param  array<string, mixed>  $json
 */
function radarAiReply(array $json): array
{
    return [
        'id' => 'chatcmpl-radar',
        'model' => 'gpt-4.1-mini',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($json, JSON_UNESCAPED_UNICODE), 'refusal' => null], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 200],
    ];
}

function laravelListing(array $overrides = []): RadarProjectData
{
    return new RadarProjectData(...[
        'externalId' => '39001122',
        'url' => 'https://www.freelancer.com/projects/php/laravel-saas',
        'title' => 'Laravel SaaS dashboard for a clinic chain',
        'description' => 'Multi-branch booking dashboard with Laravel and MySQL.',
        'budgetMinMinor' => 300000,
        'budgetMaxMinor' => 500000,
        'currency' => 'USD',
        'budgetType' => RadarBudgetType::Fixed,
        'skills' => ['PHP', 'Laravel', 'MySQL'],
        'clientRating' => 4.9,
        'clientPaymentVerified' => true,
        'proposalsCount' => 4,
        'postedAt' => CarbonImmutable::now()->subMinutes(20),
        ...$overrides,
    ]);
}

// --- Connectors & parsing ----------------------------------------------------------------------------------------

test('seeded platforms carry honest connector statuses', function () {
    $statuses = RadarPlatform::query()->pluck('status', 'key')->map->value->all();

    expect($statuses)->toBe([
        'upwork' => RadarPlatformStatus::NeedsCredentials->value,
        'freelancer' => RadarPlatformStatus::NeedsCredentials->value,
        'mostaql' => RadarPlatformStatus::NeedsCredentials->value,
        'peopleperhour' => RadarPlatformStatus::NeedsCredentials->value,
        'khamsat' => RadarPlatformStatus::Unavailable->value,
    ]);

    app(Settings::class)->set('radar', 'freelancer_api_token', 'fl-token-123', encrypted: true);
    app(RadarPlatforms::class)->refreshStatuses();

    expect($this->freelancer->fresh()->status)->toBe(RadarPlatformStatus::Active)
        ->and(RadarPlatform::query()->where('key', 'upwork')->sole()->status)->toBe(RadarPlatformStatus::NeedsCredentials);
});

test('the Freelancer connector reads the official API and normalizes projects', function () {
    app(Settings::class)->set('radar', 'freelancer_api_token', 'fl-token-123', encrypted: true);
    app(Settings::class)->set('radar', 'freelancer_query', 'laravel');
    Http::fake(['www.freelancer.com/api/projects/0.1/projects/active/*' => Http::response(radarFixture('freelancer-projects.json'))]);

    $projects = app(FreelancerConnector::class)->fetch(50);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->hasHeader('freelancer-oauth-v1', 'fl-token-123')
        && str_contains($request->url(), 'query=laravel'));

    expect($projects)->toHaveCount(2)
        ->and($projects[0]->externalId)->toBe('39001122')
        ->and($projects[0]->url)->toBe('https://www.freelancer.com/projects/php/laravel-saas-dashboard-clinic-chain')
        ->and($projects[0]->budgetMinMinor)->toBe(300000)
        ->and($projects[0]->budgetMaxMinor)->toBe(500000)
        ->and($projects[0]->currency)->toBe('USD')
        ->and($projects[0]->budgetType)->toBe(RadarBudgetType::Fixed)
        ->and($projects[0]->skills)->toBe(['PHP', 'Laravel', 'MySQL'])
        ->and($projects[0]->clientCountry)->toBe('AE')
        ->and($projects[0]->clientRating)->toBe(4.9)
        ->and($projects[0]->clientPaymentVerified)->toBeTrue()
        ->and($projects[0]->proposalsCount)->toBe(4)
        ->and($projects[1]->budgetType)->toBe(RadarBudgetType::Hourly)
        ->and($projects[1]->currency)->toBe('GBP');
});

test('the Upwork connector uses client credentials and the GraphQL search', function () {
    app(Settings::class)->set('radar', 'upwork_client_id', 'upwork-client', encrypted: false);
    app(Settings::class)->set('radar', 'upwork_client_secret', 'upwork-secret', encrypted: true);

    Http::fake([
        'www.upwork.com/api/v3/oauth2/token' => Http::response(['access_token' => 'upw-access', 'expires_in' => 3600]),
        'api.upwork.com/graphql' => Http::response(radarFixture('upwork-jobs.json')),
    ]);

    $projects = app(UpworkConnector::class)->fetch(20);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'oauth2/token') && $request['grant_type'] === 'client_credentials' && $request['client_id'] === 'upwork-client');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'graphql') && $request->hasHeader('Authorization', 'Bearer upw-access') && str_contains($request['query'], 'marketplaceJobPostingsSearch'));

    expect($projects)->toHaveCount(2)
        ->and($projects[0]->url)->toBe('https://www.upwork.com/jobs/~01abcdef1234567890')
        ->and($projects[0]->budgetMaxMinor)->toBe(400000)
        ->and($projects[0]->clientCountry)->toBe('SA')
        ->and($projects[0]->clientSpentMinor)->toBe(2500000)
        ->and($projects[0]->skills)->toBe(['Flutter', 'Firebase'])
        ->and($projects[1]->budgetType)->toBe(RadarBudgetType::Hourly)
        ->and($projects[1]->budgetMinMinor)->toBe(3000)
        ->and($projects[1]->budgetMaxMinor)->toBe(4500);
});

test('Mostaql alert emails are parsed into projects without footer links', function () {
    $projects = app(MostaqlAlertParser::class)->parse('مشاريع جديدة', radarFixture('mostaql-alert.html'), null);

    expect($projects)->toHaveCount(2)
        ->and($projects[0]->externalId)->toBe('812345')
        ->and($projects[0]->title)->toBe('تطوير منصة حجوزات باستخدام Laravel')
        ->and($projects[0]->url)->not->toContain('utm_source')
        ->and($projects[0]->budgetMinMinor)->toBe(25000)
        ->and($projects[0]->budgetMaxMinor)->toBe(50000)
        ->and($projects[0]->currency)->toBe('USD')
        ->and($projects[0]->skills)->toBe(['Laravel', 'MySQL', 'REST API'])
        ->and($projects[0]->proposalsCount)->toBe(3)
        ->and($projects[0]->description)->not->toContain('قدم عرضك')
        ->and($projects[1]->description)->not->toContain('إلغاء الاشتراك');
});

test('PeoplePerHour plain-text alerts are parsed with fixed and hourly budgets', function () {
    $projects = app(PeoplePerHourAlertParser::class)->parse('New jobs', null, radarFixture('peopleperhour-alert.txt'));

    expect($projects)->toHaveCount(2)
        ->and($projects[0]->externalId)->toBe('4213567')
        ->and($projects[0]->title)->toBe('Laravel developer to build a SaaS billing module')
        ->and($projects[0]->budgetMaxMinor)->toBe(60000)
        ->and($projects[0]->currency)->toBe('GBP')
        ->and($projects[0]->proposalsCount)->toBe(5)
        ->and($projects[1]->budgetType)->toBe(RadarBudgetType::Hourly)
        ->and($projects[1]->currency)->toBe('USD')
        ->and($projects[1]->description)->not->toContain('Manage your alerts');
});

// --- Pipeline, dedupe, scoring, filters -------------------------------------------------------------------------

test('ingesting dedupes on platform and external id and only re-scores changed listings', function () {
    $ingest = app(IngestRadarProjects::class);

    expect($ingest->handle($this->freelancer, [laravelListing(), laravelListing()]))->toBe(['created' => 1, 'updated' => 0, 'unchanged' => 0])
        ->and($ingest->handle($this->freelancer, [laravelListing()]))->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1]);

    $project = RadarProject::query()->sole();
    expect($project->evaluations()->count())->toBe(1)->and($project->language)->toBe('en');

    expect($ingest->handle($this->freelancer, [laravelListing(['budgetMaxMinor' => 800000])]))->toBe(['created' => 0, 'updated' => 1, 'unchanged' => 0])
        ->and(RadarProject::query()->count())->toBe(1)
        ->and($project->evaluations()->where('kind', RadarEvaluation::KIND_RULES)->count())->toBe(2);
});

test('Arabic listings are detected as Arabic', function () {
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing(['title' => 'تطوير منصة حجوزات', 'description' => 'نحتاج إلى مطور لبناء لوحة تحكم كاملة', 'externalId' => 'ar-1'])]);

    expect(RadarProject::query()->sole()->language)->toBe('ar');
});

test('the rule score is a deterministic weighted average with an explained breakdown', function () {
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->sole();
    $scorer = app(RadarScorer::class);

    $first = $scorer->score($project);
    $second = $scorer->score($project);

    expect($first)->toBe($second);

    $factors = collect($first['breakdown'])->keyBy('factor');
    expect($factors->keys()->all())->toBe(['skills', 'budget', 'client', 'freshness', 'competition', 'language'])
        ->and($factors['skills']['score'])->toBe(100)
        ->and($factors['skills']['params'])->toMatchArray(['matched' => 3, 'total' => 3])
        ->and($factors['budget']['score'])->toBe(100)
        ->and($factors['client']['score'])->toBe(99)
        ->and($factors['freshness']['score'])->toBe(100)
        ->and($factors['competition']['score'])->toBe(100)
        ->and($factors['language']['score'])->toBe(100);

    $expected = (int) round(collect($first['breakdown'])->sum(fn (array $f): int => $f['score'] * $f['weight']) / collect($first['breakdown'])->sum('weight'));
    expect($first['score'])->toBe($expected)->and($project->score)->toBe($expected);
});

test('scoring weights are configurable and stored with the evaluation', function () {
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing(['skills' => ['Graphic Design'], 'description' => 'A logo.', 'title' => 'Logo'])]);
    $project = RadarProject::query()->sole();
    $before = $project->score;

    $this->actingAs($this->sales)->put(route('admin.radar.settings.update'), [
        'weights' => ['skills' => 0, 'budget' => 100, 'client' => 0, 'freshness' => 0, 'competition' => 0, 'language' => 0],
        'thresholds' => ['immediate' => 90, 'normal' => 75, 'digest' => 60],
        'keywords' => "Next.js\nERP",
        'target_budget_usd' => 1000,
        'target_hourly_usd' => 25,
        'languages' => ['en', 'ar'],
        'digest_size' => 5,
    ])->assertRedirect(route('admin.radar.settings'));

    expect(app(RadarSettings::class)->weights()['budget'])->toBe(100)
        ->and(app(RadarSettings::class)->keywords())->toBe(['Next.js', 'ERP']);

    app(ScoreRadarProject::class)->handle($project->fresh());

    expect($project->fresh()->score)->toBe(100)->and($before)->toBeLessThan(100)
        ->and(AuditLog::query()->where('operation', 'radar.settings_updated')->exists())->toBeTrue();
});

test('settings reject all-zero weights and inverted thresholds', function () {
    $this->actingAs($this->sales)->put(route('admin.radar.settings.update'), [
        'weights' => ['skills' => 0, 'budget' => 0, 'client' => 0, 'freshness' => 0, 'competition' => 0, 'language' => 0],
        'thresholds' => ['immediate' => 60, 'normal' => 75, 'digest' => 90],
        'target_budget_usd' => 1000, 'target_hourly_usd' => 25, 'languages' => ['en'], 'digest_size' => 5,
    ])->assertSessionHasErrors(['weights', 'thresholds.normal', 'thresholds.digest']);
});

test('filter profiles match keywords, exclusions, skills, platforms, languages and budgets', function () {
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->with('platform')->sole();
    $matcher = app(RadarFilterMatcher::class);

    $match = fn (array $rules): bool => $matcher->matches(RadarFilter::factory()->make(['rules' => $rules]), $project);

    expect($match([]))->toBeTrue()
        ->and($match(['include_keywords' => ['laravel']]))->toBeTrue()
        ->and($match(['include_keywords' => ['flutter']]))->toBeFalse()
        ->and($match(['exclude_keywords' => ['clinic']]))->toBeFalse()
        ->and($match(['include_keywords' => ['lara']]))->toBeFalse()
        ->and($match(['skills' => ['MySQL']]))->toBeTrue()
        ->and($match(['platforms' => ['upwork']]))->toBeFalse()
        ->and($match(['platforms' => ['freelancer'], 'languages' => ['en']]))->toBeTrue()
        ->and($match(['languages' => ['ar']]))->toBeFalse()
        ->and($match(['min_budget' => 4000]))->toBeTrue()
        ->and($match(['min_budget' => 6000]))->toBeFalse()
        ->and($match(['min_score' => min(100, $project->score + 1)]))->toBe($project->score >= 100)
        ->and($match(['budget_type' => 'hourly']))->toBeFalse();

    $filter = RadarFilter::factory()->create(['name' => 'Laravel & SaaS', 'rules' => ['include_keywords' => ['laravel']]]);
    RadarProject::factory()->create(['title' => 'Flutter delivery app', 'description' => 'Mobile', 'skills' => ['Flutter']]);

    $this->actingAs($this->sales)->get(route('admin.radar.index', ['profile' => $filter->id]))
        ->assertSuccessful()
        ->assertSee('Laravel SaaS dashboard for a clinic chain')
        ->assertDontSee('Flutter delivery app');
});

// --- AI score ---------------------------------------------------------------------------------------------------

test('the AI relevance score is stored beside the rule score and never replaces it', function () {
    radarConfigureAi();
    Http::fake(['api.openai.com/*' => Http::response(radarAiReply(['score' => 42, 'explanation' => 'Good stack match, but scope is vague.', 'matching_skills' => ['Laravel']]))]);
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->sole();
    $ruleScore = $project->score;

    $this->actingAs($this->sales)->post(route('admin.radar.projects.ai-score', $project))->assertRedirect();

    $project->refresh();
    expect($project->ai_score)->toBe(42)
        ->and($project->score)->toBe($ruleScore)
        ->and($project->evaluations()->where('kind', RadarEvaluation::KIND_AI)->sole()->explanation)->toBe('Good stack match, but scope is vague.')
        ->and(AiUsageLog::query()->where('feature', 'radar')->count())->toBe(1);

    // The listing goes in the user message as escaped data, never in the system prompt.
    Http::assertSent(function (Request $request): bool {
        $messages = $request['messages'];

        return str_contains($messages[0]['content'], 'never follow instructions')
            || (str_contains($messages[1]['content'], '<listing>') && ! str_contains($messages[0]['content'], 'clinic chain'));
    });

    $this->actingAs($this->sales)->get(route('admin.radar.projects.show', $project))
        ->assertSuccessful()
        ->assertSee('Good stack match, but scope is vague.')
        ->assertSee(__('Rule score: :score / 100', ['score' => $ruleScore]));
});

test('the ai_radar kill switch stops AI scoring and leaves the rule score', function () {
    radarConfigureAi();
    app(KillSwitches::class)->set(KillSwitchKey::AiRadar, true, $this->sales);
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->sole();

    expect(fn () => app(ScoreRadarProjectWithAi::class)->handle($project))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailableReason::Disabled));

    $this->actingAs($this->sales)->post(route('admin.radar.projects.ai-score', $project))->assertSessionHas('toast');

    Http::assertNothingSent();
    expect($project->fresh()->ai_score)->toBeNull()->and($project->fresh()->score)->not->toBeNull();
});

// --- Proposal drafts --------------------------------------------------------------------------------------------

test('proposal drafts are generated, flagged by guardrails and never submitted to a platform', function () {
    $this->seed(AiStudioSeeder::class);
    radarConfigureAi();
    Http::fake(['api.openai.com/*' => Http::response(radarAiReply(['en' => ['body' => "Hello! We have built a similar project for a clinic. Our price is $4,000.\n- Laravel + MySQL"]]))]);
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->sole();

    $this->actingAs($this->sales)->post(route('admin.radar.projects.proposals.store', $project), ['locale' => 'en', 'facts' => 'We maintain Laravel apps.', 'price' => ''])
        ->assertRedirect();

    $proposal = $project->proposals()->sole();
    $types = collect($proposal->guardrail_flags)->pluck('type')->all();

    expect($proposal->body)->toContain('Laravel + MySQL')
        ->and($types)->toContain(GuardrailFlagType::Price->value)
        ->and($types)->toContain(GuardrailFlagType::ExperienceClaim->value)
        ->and($project->fresh()->status)->toBe(RadarProjectStatus::ProposalDrafted);

    // The only outbound call was the AI provider: nothing went to Freelancer or any other platform.
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), 'api.openai.com'));

    $this->actingAs($this->sales)->put(route('admin.radar.projects.proposals.update', [$project, $proposal]), ['body' => 'Edited by a human.'])->assertRedirect();
    expect($proposal->fresh()->body)->toBe('Edited by a human.');

    $this->actingAs($this->sales)->get(route('admin.radar.projects.show', $project))
        ->assertSuccessful()
        ->assertSee(__('Drafts are never submitted automatically. Review, edit and copy the text, then send it on the platform yourself.'))
        ->assertDontSee('Submit to');
});

test('an entered price may appear in the proposal without a price flag', function () {
    $this->seed(AiStudioSeeder::class);
    radarConfigureAi();
    Http::fake(['api.openai.com/*' => Http::response(radarAiReply(['en' => ['body' => 'We can deliver this for USD 4000.']]))]);
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);

    $this->actingAs($this->sales)->post(route('admin.radar.projects.proposals.store', RadarProject::query()->sole()), ['locale' => 'en', 'price' => 'USD 4000'])->assertRedirect();

    expect(RadarProject::query()->sole()->proposals()->sole()->guardrail_flags)->toBe([]);
});

// --- Telegram ---------------------------------------------------------------------------------------------------

test('MarkdownV2 escaping covers every reserved character', function () {
    expect(TelegramNotifier::escape('Price: $1,000.50 (fixed) - [v2] #1 a_b*c~d`e>f+g=h|i{j}k!l\\'))
        ->toBe('Price: $1,000\\.50 \\(fixed\\) \\- \\[v2\\] \\#1 a\\_b\\*c\\~d\\`e\\>f\\+g\\=h\\|i\\{j\\}k\\!l\\\\')
        ->and(TelegramNotifier::escapeUrl('https://x.test/a_(b)'))->toBe('https://x.test/a_(b\\)');
});

test('projects above the immediate threshold alert once on Telegram; unchanged projects never alert again', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $ingest = app(IngestRadarProjects::class);
    $ingest->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->sole();

    expect($project->score)->toBeGreaterThanOrEqual(90);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/bot123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsawQ/sendMessage')
        && $request['parse_mode'] === 'MarkdownV2'
        && $request['chat_id'] === '-1001234567890'
        && str_contains($request['text'], '*Laravel SaaS dashboard for a clinic chain*')
        && str_contains($request['text'], 'USD 3,000 – USD 5,000')
        && str_contains($request['text'], '[Open project](https://www.freelancer.com/projects/php/laravel-saas)'));

    $alert = RadarAlert::query()->sole();
    expect($alert->level)->toBe(RadarAlertLevel::Immediate)->and($alert->status)->toBe('sent');

    // Same listing collected again, and a re-score with unchanged content: no new alert.
    $ingest->handle($this->freelancer, [laravelListing()]);
    app(RadarAlerts::class)->consider($project->fresh());

    Http::assertSentCount(1);
    expect(RadarAlert::query()->count())->toBe(1);

    $this->actingAs($this->sales)->get(route('admin.radar.index'))->assertSee(__('Telegram alert sent'));
});

test('alerting filter profiles gate alerts, and low scores are saved without notification', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    RadarFilter::factory()->create(['rules' => ['include_keywords' => ['flutter']]]);

    app(IngestRadarProjects::class)->handle($this->freelancer, [
        laravelListing(),
        laravelListing(['externalId' => 'low-1', 'title' => 'Translate a brochure', 'description' => 'Translation only', 'skills' => ['Translation'], 'budgetMaxMinor' => 2000, 'budgetMinMinor' => 1000, 'proposalsCount' => 80, 'postedAt' => CarbonImmutable::now()->subDays(5)]),
    ]);

    Http::assertNothingSent();
    expect(RadarAlert::query()->count())->toBe(0)
        ->and(RadarProject::query()->count())->toBe(2);
});

test('mid-band projects wait for the daily digest, which sends the top N once', function () {
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    app(RadarSettings::class)->save(['thresholds' => ['immediate' => 101, 'normal' => 101, 'digest' => 50], 'digest_size' => 1], $this->sales);

    app(IngestRadarProjects::class)->handle($this->freelancer, [
        laravelListing(),
        laravelListing(['externalId' => 'b-2', 'title' => 'Vue widget fix', 'skills' => ['Vue'], 'budgetMaxMinor' => 20000, 'budgetMinMinor' => 10000]),
    ]);

    Http::assertNothingSent();
    expect(RadarAlert::query()->where('level', RadarAlertLevel::Digest)->where('status', 'pending')->count())->toBe(2);

    Artisan::call('radar:digest');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'Freelance Radar daily digest')
        && str_contains($request['text'], 'Top 1 of 2')
        && str_contains($request['text'], 'Laravel SaaS dashboard for a clinic chain')
        && ! str_contains($request['text'], 'Vue widget fix'));

    expect(RadarAlert::query()->where('status', 'sent')->count())->toBe(1)
        ->and(RadarAlert::query()->where('status', 'skipped')->count())->toBe(1);

    Artisan::call('radar:digest');
    Http::assertSentCount(1);
});

test('the Telegram test button reports an honest status', function () {
    $this->actingAs($this->sales)->get(route('admin.radar.settings'))->assertSuccessful()->assertSee(__('Requires credentials'));

    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::sequence()
        ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
        ->push(['ok' => true])]);

    $this->actingAs($this->sales)->post(route('admin.radar.telegram.test'))->assertSessionHas('toast', fn (string $toast): bool => str_contains($toast, 'chat not found'));
    $this->actingAs($this->sales)->get(route('admin.radar.settings'))->assertSee(__('Credentials saved · not verified'));

    $this->actingAs($this->sales)->post(route('admin.radar.telegram.test'))->assertSessionHas('toast', __('Test message sent to Telegram.'));
    $this->actingAs($this->sales)->get(route('admin.radar.settings'))->assertSee(__('Test message delivered'));
});

// --- Fetch command & kill switches ------------------------------------------------------------------------------

test('radar:fetch collects due API platforms and respects the per-platform switch and kill switch', function () {
    app(Settings::class)->set('radar', 'freelancer_api_token', 'fl-token-123', encrypted: true);
    Http::fake(['www.freelancer.com/*' => Http::response(radarFixture('freelancer-projects.json'))]);

    $this->freelancer->update(['is_enabled' => false]);
    Artisan::call('radar:fetch');
    Http::assertNothingSent();

    $this->freelancer->update(['is_enabled' => true]);
    app(KillSwitches::class)->set(KillSwitchKey::RadarCollection, true, $this->sales);
    Artisan::call('radar:fetch');
    Http::assertNothingSent();

    app(KillSwitches::class)->set(KillSwitchKey::RadarCollection, false, $this->sales);
    Artisan::call('radar:fetch');

    expect(RadarProject::query()->count())->toBe(2)
        ->and($this->freelancer->fresh()->last_success_at)->not->toBeNull()
        ->and($this->freelancer->fresh()->status)->toBe(RadarPlatformStatus::Active);

    // Not due again until its cadence has passed.
    Artisan::call('radar:fetch');
    Http::assertSentCount(1);
});

test('a failing API run is recorded as an Error status without fake data', function () {
    app(Settings::class)->set('radar', 'freelancer_api_token', 'fl-token-123', encrypted: true);
    Http::fake(['www.freelancer.com/*' => Http::response(['status' => 'error'], 401)]);

    $this->actingAs($this->sales)->post(route('admin.radar.platforms.fetch', $this->freelancer))->assertSessionHas('toast');

    expect($this->freelancer->fresh()->status)->toBe(RadarPlatformStatus::Error)
        ->and($this->freelancer->fresh()->last_error)->toContain('401')
        ->and(RadarProject::query()->count())->toBe(0);
});

// --- Status, lead conversion, permissions, rendering ------------------------------------------------------------

test('a radar project converts to a lead with source radar', function () {
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->sole();

    $this->actingAs($this->sales)->post(route('admin.radar.projects.convert', $project), [
        'name' => 'Clinic chain owner', 'email' => 'freelancer-39001122@radar.invalid', 'preferred_locale' => 'en',
    ])->assertRedirect();

    $lead = Lead::query()->sole();
    expect($lead->source)->toBe(LeadSource::Radar)
        ->and($lead->brief['radar_project_id'])->toBe($project->id)
        ->and($lead->estimated_value_minor)->toBe(500000)
        ->and($project->fresh()->lead_id)->toBe($lead->id)
        ->and($project->fresh()->status)->toBe(RadarProjectStatus::Applied)
        ->and(AuditLog::query()->where('operation', 'radar.converted_to_lead')->exists())->toBeTrue();

    $this->actingAs($this->sales)->post(route('admin.radar.projects.convert', $project), [
        'name' => 'Again', 'email' => 'again@radar.invalid', 'preferred_locale' => 'en',
    ])->assertSessionHasErrors('project');
});

test('staff change a project status and it is audited', function () {
    $project = RadarProject::factory()->create();

    $this->actingAs($this->sales)->post(route('admin.radar.projects.status', $project), ['status' => 'won'])->assertRedirect();
    $this->actingAs($this->sales)->post(route('admin.radar.projects.status', $project), ['status' => 'bogus'])->assertSessionHasErrors('status');

    expect($project->fresh()->status)->toBe(RadarProjectStatus::Won)
        ->and(AuditLog::query()->where('operation', 'radar.status_changed')->exists())->toBeTrue();
});

test('radar screens need radar.view and changes need radar.manage', function () {
    $project = RadarProject::factory()->create();
    $content = User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create();

    $this->actingAs($content)->get(route('admin.radar.index'))->assertForbidden();
    $this->actingAs($content)->get(route('admin.radar.projects.show', $project))->assertForbidden();
    $this->actingAs($content)->post(route('admin.radar.projects.status', $project), ['status' => 'won'])->assertForbidden();
    $this->actingAs($content)->get(route('admin.radar.settings'))->assertForbidden();

    $viewer = User::factory()->role(Role::SalesManager)->create();
    $viewer->syncRoles([]);
    $viewer->givePermissionTo(['admin.access', 'radar.view']);

    $this->actingAs($viewer)->get(route('admin.radar.index'))->assertSuccessful();
    $this->actingAs($viewer)->get(route('admin.radar.analytics'))->assertSuccessful();
    $this->actingAs($viewer)->post(route('admin.radar.projects.status', $project), ['status' => 'won'])->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.radar.projects.proposals.store', $project), ['locale' => 'en'])->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.radar.settings'))->assertForbidden();
    $this->actingAs($viewer)->post(route('admin.radar.filters.store'), ['name' => 'x'])->assertForbidden();
});

test('filter profiles can be created, edited and deleted', function () {
    $this->actingAs($this->sales)->post(route('admin.radar.filters.store'), [
        'name' => 'Laravel & SaaS', 'include_keywords' => "laravel\nsaas", 'exclude_keywords' => 'wordpress', 'skills' => '',
        'countries' => 'ae, sa', 'platforms' => ['freelancer'], 'languages' => ['en'], 'min_budget' => 500, 'is_active' => '1', 'alerts_enabled' => '1',
    ])->assertRedirect(route('admin.radar.settings'));

    $filter = RadarFilter::query()->sole();
    expect($filter->rules['include_keywords'])->toBe(['laravel', 'saas'])
        ->and($filter->rules['countries'])->toBe(['AE', 'SA']);

    $this->actingAs($this->sales)->post(route('admin.radar.filters.store'), ['name' => '', 'countries' => 'egypt'])->assertSessionHasErrors(['name', 'countries.0']);

    $this->actingAs($this->sales)->get(route('admin.radar.filters.edit', $filter))->assertSuccessful()->assertSee('saas');
    $this->actingAs($this->sales)->put(route('admin.radar.filters.update', $filter), ['name' => 'Laravel only', 'include_keywords' => 'laravel'])->assertRedirect();
    expect($filter->fresh()->name)->toBe('Laravel only');

    $this->actingAs($this->sales)->delete(route('admin.radar.filters.destroy', $filter))->assertRedirect();
    expect(RadarFilter::query()->count())->toBe(0);
});

test('the radar dashboard, analytics and settings render in English and Arabic', function () {
    app(IngestRadarProjects::class)->handle($this->freelancer, [laravelListing()]);
    $project = RadarProject::query()->sole();
    $project->update(['status' => RadarProjectStatus::Won]);

    $this->actingAs($this->sales)->get(route('admin.radar.index'))
        ->assertSuccessful()
        ->assertSee('Freelance Projects Radar')
        ->assertSee('Khamsat')
        ->assertSee(__('Unavailable'))
        ->assertSee('Laravel SaaS dashboard for a clinic chain');

    $this->actingAs($this->sales)->get(route('admin.radar.analytics'))
        ->assertSuccessful()
        ->assertSee('data-analytics-chart="radar-daily-chart"', false)
        ->assertSee('Freelancer');

    $this->sales->update(['locale' => 'ar']);

    foreach ([route('admin.radar.index'), route('admin.radar.analytics'), route('admin.radar.settings'), route('admin.radar.projects.show', $project), route('admin.radar.filters.create')] as $url) {
        $this->actingAs($this->sales)->get($url)->assertSuccessful()->assertSee('dir="rtl"', false);
    }

    $this->actingAs($this->sales)->get(route('admin.radar.index'))->assertSee('رادار المشاريع الحرة');
});
