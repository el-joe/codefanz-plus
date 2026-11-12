<?php

use App\Contracts\AiProvider;
use App\Enums\AiFeature;
use App\Enums\AiUnavailableReason;
use App\Enums\AiUsageStatus;
use App\Enums\KillSwitchKey;
use App\Enums\Role;
use App\Exceptions\AiUnavailable;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\Ai\AiBudget;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\NullAiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\KillSwitches;
use App\Services\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
});

function configureAi(string $provider = 'openai', ?string $model = null): void
{
    $settings = app(Settings::class);
    $settings->set('integrations', 'ai_provider', $provider);
    $settings->set('integrations', $provider.'_api_key', 'test-key-'.$provider, encrypted: true);

    if ($model !== null) {
        $settings->set('integrations', $provider.'_model', $model);
    }
}

/**
 * @return array<string, mixed>
 */
function openAiReply(string $content, int $input = 1000, int $output = 500, string $model = 'gpt-4.1-mini'): array
{
    return [
        'id' => 'chatcmpl-test',
        'model' => $model,
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content, 'refusal' => null], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => $input, 'completion_tokens' => $output],
    ];
}

function sampleRequest(): AiRequest
{
    return new AiRequest(
        system: 'You are a test.',
        messages: [['role' => 'user', 'content' => 'Hello there']],
        schema: ['type' => 'object', 'properties' => ['reply' => ['type' => 'string']], 'required' => ['reply'], 'additionalProperties' => false],
        schemaName: 'test_reply',
        maxTokens: 300,
    );
}

test('without saved credentials the null provider is used and nothing is sent', function () {
    $gateway = app(AiGateway::class);

    expect($gateway->provider())->toBeInstanceOf(NullAiProvider::class)
        ->and(app(AiProvider::class))->toBeInstanceOf(NullAiProvider::class)
        ->and($gateway->unavailableReason(AiFeature::ProjectAssistant))->toBe(AiUnavailableReason::NotConfigured);

    expect(fn () => $gateway->complete(AiFeature::ProjectAssistant, sampleRequest()))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailableReason::NotConfigured));

    Http::assertNothingSent();
    expect(AiUsageLog::count())->toBe(0);
});

test('openai is selected from settings and receives a chat completions request with a strict json schema', function () {
    configureAi('openai', 'gpt-4.1-mini');
    Http::fake(['api.openai.com/*' => Http::response(openAiReply('{"reply":"Hi"}'))]);

    $gateway = app(AiGateway::class);
    expect($gateway->provider())->toBeInstanceOf(OpenAiProvider::class);

    $response = $gateway->complete(AiFeature::ProjectAssistant, sampleRequest());

    expect($response->json)->toBe(['reply' => 'Hi'])
        ->and($response->inputTokens)->toBe(1000)
        ->and($response->outputTokens)->toBe(500);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key-openai')
            && $request['model'] === 'gpt-4.1-mini'
            && $request['messages'][0] === ['role' => 'system', 'content' => 'You are a test.']
            && $request['messages'][1] === ['role' => 'user', 'content' => 'Hello there']
            && $request['max_completion_tokens'] === 300
            && $request['response_format']['type'] === 'json_schema'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['response_format']['json_schema']['name'] === 'test_reply';
    });
});

test('anthropic is selected from settings and receives a messages api request', function () {
    configureAi('anthropic', 'claude-haiku-5-5');
    Http::fake(['api.anthropic.com/*' => Http::response([
        'id' => 'msg_test',
        'model' => 'claude-haiku-5-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => '{"reply":"Hello"}']],
        'usage' => ['input_tokens' => 2000, 'output_tokens' => 400],
    ])]);

    $gateway = app(AiGateway::class);
    expect($gateway->provider())->toBeInstanceOf(AnthropicProvider::class);

    $response = $gateway->complete(AiFeature::ProjectAssistant, sampleRequest());

    expect($response->json)->toBe(['reply' => 'Hello'])->and($response->model)->toBe('claude-haiku-5-5');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'test-key-anthropic')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'claude-haiku-5-5'
            && $request['system'] === 'You are a test.'
            && $request['messages'] === [['role' => 'user', 'content' => 'Hello there']]
            && $request['max_tokens'] === 300
            && $request['output_config']['format']['type'] === 'json_schema'
            && ! isset($request['temperature']);
    });

    // 2000 × $0.10 + 400 × $0.50 per million tokens = $0.0004 = 0.04 cents.
    expect(AiUsageLog::sole()->estimated_cost_minor)->toBe(0.04);
});

test('rate limits and server errors are retried with backoff', function () {
    configureAi();
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(['error' => ['message' => 'slow down']], 429)
        ->push(['error' => ['message' => 'oops']], 503)
        ->push(openAiReply('{"reply":"Third time"}'))]);

    $response = app(AiGateway::class)->complete(AiFeature::ProjectAssistant, sampleRequest());

    expect($response->json['reply'])->toBe('Third time');
    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
    expect(AiUsageLog::sole()->status)->toBe(AiUsageStatus::Succeeded);
});

test('client errors are not retried and are logged as failures without the request body', function () {
    configureAi();
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'bad request containing Hello there']], 400)]);

    expect(fn () => app(AiGateway::class)->complete(AiFeature::ProjectAssistant, sampleRequest(), ['chat_session_id' => null]))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailableReason::Failed));

    Http::assertSentCount(1);
    $log = AiUsageLog::sole();
    expect($log->status)->toBe(AiUsageStatus::Failed)
        ->and($log->error)->toBe('HTTP 400')
        ->and($log->estimated_cost_minor)->toBe(0.0);
});

test('exhausted retries fail over to the caller', function () {
    configureAi();
    Http::fake(['api.openai.com/*' => Http::response([], 500)]);

    expect(fn () => app(AiGateway::class)->complete(AiFeature::ProjectAssistant, sampleRequest()))->toThrow(AiUnavailable::class);

    Http::assertSentCount(3);
    expect(AiUsageLog::sole()->error)->toBe('HTTP 500');
});

test('a successful call logs tokens, latency and the estimated cost from the price table', function () {
    configureAi();
    $user = User::factory()->role(Role::ContentManager)->create();
    Http::fake(['api.openai.com/*' => Http::response(openAiReply('{"reply":"ok"}', 1000, 500, 'gpt-4.1-mini-2025-04-14'))]);

    app(AiGateway::class)->complete(AiFeature::ContentStudio, sampleRequest(), ['user_id' => $user->id]);

    $log = AiUsageLog::sole();
    // 1000 × $0.40 + 500 × $1.60 per million = $0.0012 = 0.12 cents (dated model ids use the base price).
    expect($log->feature)->toBe(AiFeature::ContentStudio)
        ->and($log->provider)->toBe('openai')
        ->and($log->model)->toBe('gpt-4.1-mini-2025-04-14')
        ->and($log->input_tokens)->toBe(1000)
        ->and($log->output_tokens)->toBe(500)
        ->and($log->estimated_cost_minor)->toBe(0.12)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->latency_ms)->toBeGreaterThanOrEqual(0);

    expect(app(AiBudget::class)->estimateCostMinor('unknown-model', 1_000_000, 0))->toBe(500.0);
});

test('the monthly budget is a hard stop', function () {
    configureAi();
    app(Settings::class)->set('integrations', 'ai_monthly_budget', '1');
    AiUsageLog::factory()->create(['estimated_cost_minor' => 100]);
    // Last month's spend does not count.
    AiUsageLog::factory()->create(['estimated_cost_minor' => 5000, 'created_at' => now()->subMonthNoOverflow()]);
    Http::fake();

    $gateway = app(AiGateway::class);
    expect($gateway->unavailableReason(AiFeature::ProjectAssistant))->toBe(AiUnavailableReason::BudgetExceeded);
    expect(fn () => $gateway->complete(AiFeature::ProjectAssistant, sampleRequest()))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailableReason::BudgetExceeded));

    Http::assertNothingSent();

    app(Settings::class)->set('integrations', 'ai_monthly_budget', '5');
    expect($gateway->unavailableReason(AiFeature::ProjectAssistant))->toBeNull();
});

test('each feature honours its own kill switch', function () {
    configureAi();
    $admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create();
    app(KillSwitches::class)->set(KillSwitchKey::AiAssistant, true, $admin);
    Http::fake();

    $gateway = app(AiGateway::class);
    expect($gateway->unavailableReason(AiFeature::ProjectAssistant))->toBe(AiUnavailableReason::Disabled)
        ->and($gateway->unavailableReason(AiFeature::ContentStudio))->toBeNull();

    app(KillSwitches::class)->set(KillSwitchKey::AiAutomation, true, $admin);
    expect($gateway->unavailableReason(AiFeature::ContentStudio))->toBe(AiUnavailableReason::Disabled);

    Http::assertNothingSent();
});
