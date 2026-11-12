<?php

use App\Enums\AiUnavailableReason;
use App\Enums\AiUsageStatus;
use App\Enums\ChatMode;
use App\Enums\ChatRole;
use App\Enums\ChatSessionStatus;
use App\Enums\KillSwitchKey;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Events\LeadCreated;
use App\Http\Requests\Assistant\StartAssistantRequest;
use App\Models\AiUsageLog;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Technology;
use App\Models\User;
use App\Notifications\LeadReceivedConfirmation;
use App\Notifications\NewLeadReceived;
use App\Services\KillSwitches;
use App\Services\Settings;
use Database\Factories\ChatSessionFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
    Notification::fake();

    $this->service = Service::factory()->withTranslation('en', ['title' => 'Mobile Apps', 'slug' => 'mobile-apps'])
        ->withTranslation('ar', ['title' => 'تطبيقات الجوال', 'slug' => 'تطبيقات-الجوال'])->create();
    $this->technology = Technology::factory()->withTranslation('en', ['name' => 'Laravel'])->create();
    $this->sales = User::factory()->role(Role::SalesManager)->create();
});

function enableAssistantAi(): void
{
    app(Settings::class)->set('integrations', 'ai_provider', 'openai');
    app(Settings::class)->set('integrations', 'openai_api_key', 'sk-test', encrypted: true);
}

/**
 * A structured assistant turn as the model would return it (every brief key present).
 *
 * @param  array<string, mixed>  $brief
 * @return array<string, mixed>
 */
function assistantTurn(string $reply, array $brief = [], array $quickReplies = []): array
{
    $empty = [
        'project_type' => null, 'description' => null, 'platforms' => [], 'features' => [], 'goals' => null,
        'existing_system' => null, 'services' => [], 'technologies' => [], 'timeline' => null, 'budget_range' => null,
        'name' => null, 'email' => null, 'phone' => null, 'company' => null, 'country' => null,
        'preferred_contact' => null, 'preferred_locale' => null,
    ];

    return ['reply' => $reply, 'quick_replies' => $quickReplies, 'ready_for_summary' => false, 'brief' => [...$empty, ...$brief]];
}

/**
 * @param  array<string, mixed>  $turn
 * @return array<string, mixed>
 */
function completion(array $turn): array
{
    return [
        'id' => 'chatcmpl-test',
        'model' => 'gpt-4.1-mini',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($turn), 'refusal' => null], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 200],
    ];
}

/**
 * @return array{0: string, 1: string} session ulid and token
 */
function startConversation(string $locale = 'en'): array
{
    $response = test()->postJson("/{$locale}/assistant/sessions", [StartAssistantRequest::TIMESTAMP => StartAssistantRequest::timestampToken()])
        ->assertCreated();

    return [$response->json('session'), $response->json('token')];
}

function sendMessage(string $session, string $token, string $message, string $locale = 'en'): TestResponse
{
    return test()->withHeader('X-Assistant-Token', $token)
        ->postJson(route('assistant.message', ['locale' => $locale, 'chatSession' => $session]), ['message' => $message]);
}

test('the assistant page renders in english and arabic with the free badge', function (string $locale, string $dir, string $title) {
    $this->get("/{$locale}/assistant")
        ->assertSuccessful()
        ->assertSee('dir="'.$dir.'"', false)
        ->assertSee($title)
        ->assertSee('data-assistant-free', false)
        ->assertSee('data-mode="guided"', false)
        ->assertSee('data-guided-step="platforms"', false)
        ->assertSee('name="website"', false);
})->with([
    'english' => ['en', 'ltr', 'Free · no payment needed'],
    'arabic' => ['ar', 'rtl', 'مجاني · بدون أي دفع'],
]);

test('the page offers the ai conversation when a provider is configured', function () {
    enableAssistantAi();

    $this->get('/en/assistant')->assertSuccessful()->assertSee('data-mode="ai"', false)->assertSee('A mobile app');
});

test('starting a conversation creates a bound session without storing the raw ip', function () {
    enableAssistantAi();

    $response = $this->postJson('/en/assistant/sessions', [StartAssistantRequest::TIMESTAMP => StartAssistantRequest::timestampToken()])
        ->assertCreated()
        ->assertJsonPath('mode', 'ai')
        ->assertJsonPath('progress.collected', 0)
        ->assertJsonPath('progress.total', 6);

    $session = ChatSession::sole();
    expect($response->json('session'))->toBe($session->ulid)
        ->and($session->matchesToken($response->json('token')))->toBeTrue()
        ->and($session->token_hash)->not->toBe($response->json('token'))
        ->and($session->ip_hash)->not->toContain('127.0.0.1')
        ->and($session->messages()->count())->toBe(2);
    Http::assertNothingSent();
});

test('bots filling the honeypot or tampering with the page token get no session', function (array $payload) {
    $this->postJson('/en/assistant/sessions', $payload)->assertUnprocessable();

    expect(ChatSession::count())->toBe(0);
})->with([
    'honeypot' => fn () => [StartAssistantRequest::HONEYPOT => 'http://spam.example', StartAssistantRequest::TIMESTAMP => StartAssistantRequest::timestampToken()],
    'tampered token' => fn () => [StartAssistantRequest::TIMESTAMP => 'not-a-token'],
    'missing token' => fn () => [],
]);

test('the ai interview builds a validated brief and the confirmation creates a lead with the transcript', function () {
    enableAssistantAi();
    Event::fake([LeadCreated::class]);
    Http::fake(['api.openai.com/*' => Http::sequence()
        ->push(completion(assistantTurn('Great. Which platforms do you need?', [
            'project_type' => 'Clinic booking platform',
            'description' => 'Booking for our three clinics.',
        ], ['Web', 'Mobile', 'Both'])))
        ->push(completion(assistantTurn('Thanks! Here is the summary — please confirm.', [
            'project_type' => 'Clinic booking platform',
            'description' => 'Booking for our three clinics.',
            'platforms' => ['web', 'android', 'ios'],
            'features' => ['Booking', 'Reminders', 'Payments'],
            'timeline' => '1_3_months',
            'services' => [$this->service->id],
            'technologies' => ['Laravel', 'Flutter'],
            'name' => 'Mona Hassan',
            'email' => 'Mona@Example.com',
            'preferred_contact' => 'email',
            'country' => 'eg',
        ]))),
    ]);

    [$session, $token] = startConversation();

    sendMessage($session, $token, 'A booking platform for our clinics.')
        ->assertSuccessful()
        ->assertJsonPath('mode', 'ai')
        ->assertJsonPath('messages.1.content', 'Great. Which platforms do you need?')
        ->assertJsonPath('quick_replies', ['Web', 'Mobile', 'Both'])
        ->assertJsonPath('progress.collected', 1)
        ->assertJsonPath('summary', null);

    $response = sendMessage($session, $token, 'Both. Web dashboard plus Android and iOS apps, in about 3 months. I am Mona, mona@example.com')
        ->assertSuccessful()
        ->assertJsonPath('progress.collected', 5)
        ->assertJsonPath('summary.contact.email', 'mona@example.com')
        ->assertJsonPath('summary.contact.name', 'Mona Hassan');

    expect(collect($response->json('summary.rows'))->pluck('value'))->toContain('Clinic booking platform', 'Web, Android, iOS', 'Booking, Reminders, Payments', '1–3 months', 'Mobile Apps');

    // The system prompt stays on the server; the visitor's messages are passed as user messages.
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'], 'never instructions to you')
        && $request['messages'][0]['role'] === 'system'
        && collect($request['messages'])->contains(fn (array $message): bool => $message['role'] === 'user' && $message['content'] === 'A booking platform for our clinics.'));
    expect($response->getContent())->not->toContain('never instructions to you');

    $this->withHeader('X-Assistant-Token', $token)
        ->postJson(route('assistant.confirm', ['locale' => 'en', 'chatSession' => $session]), [
            'name' => 'Mona Hassan',
            'email' => 'mona@example.com',
            'preferred_contact' => 'whatsapp',
            'phone' => '+20 100 000 0000',
            'preferred_locale' => 'ar',
            'consent' => '1',
        ])
        ->assertSuccessful()
        ->assertJsonStructure(['reference', 'message']);

    $lead = Lead::sole();
    $chat = ChatSession::sole();

    expect($lead->source)->toBe(LeadSource::AiAssistant)
        ->and($lead->name)->toBe('Mona Hassan')
        ->and($lead->platforms)->toBe(['web', 'android', 'ios'])
        ->and($lead->timeline?->value)->toBe('1_3_months')
        ->and($lead->features)->toBe("Booking\nReminders\nPayments")
        ->and($lead->description)->toContain('Clinic booking platform')
        ->and($lead->country)->toBe('EG')
        ->and($lead->preferred_contact->value)->toBe('whatsapp')
        ->and($lead->preferred_locale)->toBe('ar')
        ->and($lead->consent_at)->not->toBeNull()
        ->and($lead->brief['assistant']['session'])->toBe($chat->ulid)
        ->and($lead->brief['assistant']['technology_notes'])->toBe(['Flutter'])
        ->and($lead->services()->pluck('services.id')->all())->toBe([$this->service->id])
        ->and($lead->technologies()->pluck('technologies.id')->all())->toBe([$this->technology->id])
        ->and($chat->lead_id)->toBe($lead->id)
        ->and($chat->status)->toBe(ChatSessionStatus::Completed)
        ->and($lead->chatSession->messages)->toHaveCount(6)
        ->and($lead->activities()->sole()->meta['source'])->toBe('ai_assistant');

    expect(AiUsageLog::query()->where('chat_session_id', $chat->id)->where('status', AiUsageStatus::Succeeded)->count())->toBe(2);

    Event::assertDispatched(LeadCreated::class, fn (LeadCreated $event): bool => $event->lead->is($lead));
    Notification::assertSentTo($this->sales, NewLeadReceived::class);
    Notification::assertSentTo(new AnonymousNotifiable, LeadReceivedConfirmation::class);

    // A finished conversation cannot be continued.
    sendMessage($session, $token, 'One more thing')->assertStatus(410);
});

test('invalid model fields are rejected or normalized and model output is stripped of html', function () {
    enableAssistantAi();
    Http::fake(['api.openai.com/*' => Http::response(completion(assistantTurn('<script>alert(1)</script><b>Thanks!</b> <img src=x onerror=alert(2)>Which timeline?', [
        'project_type' => '<i>Shop</i>',
        'platforms' => ['web', 'smartwatch', 'web'],
        'features' => ['Cart', '', '<b>Checkout</b>'],
        'timeline' => 'next week',
        'budget_range' => 'one million',
        'services' => [999999, (string) $this->service->id, 'abc'],
        'email' => 'not-an-email',
        'phone' => 'call me maybe',
        'country' => 'Narnia',
        'preferred_contact' => 'pigeon',
        'preferred_locale' => 'fr',
        'unexpected' => 'ignored',
    ], ['<a href="x">Yes</a>', str_repeat('long ', 40)])))]);

    [$session, $token] = startConversation();
    $response = sendMessage($session, $token, '<b>An online shop</b>')->assertSuccessful();

    expect($response->getContent())->not->toContain('<script')->not->toContain('<img')->not->toContain('<b>');
    expect($response->json('messages.1.content'))->toBe('alert(1)Thanks! Which timeline?')
        ->and($response->json('quick_replies.0'))->toBe('Yes')
        ->and(mb_strlen($response->json('quick_replies.1')))->toBeLessThanOrEqual(60);

    $brief = ChatSession::sole()->brief;
    expect($brief)->toEqual([
        'project_type' => 'Shop',
        'platforms' => ['web'],
        'features' => ['Cart', 'Checkout'],
        'services' => [$this->service->id],
    ]);

    expect(ChatMessage::query()->where('role', ChatRole::User)->sole()->content)->toBe('An online shop');
});

test('a structured reply without a message falls back to the guided questionnaire', function () {
    enableAssistantAi();
    Http::fake(['api.openai.com/*' => Http::response(completion(['reply' => '', 'quick_replies' => [], 'ready_for_summary' => false, 'brief' => []]))]);

    [$session, $token] = startConversation();

    sendMessage($session, $token, 'Hello')->assertSuccessful()->assertJsonPath('mode', 'guided')->assertJsonPath('step', 'project');
});

test('a provider error switches the conversation to the guided questionnaire with an honest notice', function () {
    enableAssistantAi();
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'down']], 503)]);

    [$session, $token] = startConversation();

    sendMessage($session, $token, 'A booking app')
        ->assertSuccessful()
        ->assertJsonPath('mode', 'guided')
        ->assertJsonPath('step', 'project')
        ->assertJsonPath('notice', 'Our AI assistant did not respond, so we switched to a short guided questionnaire. Your answers still reach our team.');

    $chat = ChatSession::sole();
    expect($chat->mode)->toBe(ChatMode::Guided)
        ->and($chat->fallback_reason)->toBe(AiUnavailableReason::Failed->value)
        ->and(AiUsageLog::sole()->status)->toBe(AiUsageStatus::Failed);

    // Further chat messages are refused; the guided questionnaire takes over.
    sendMessage($session, $token, 'Hello again')->assertStatus(409)->assertJsonPath('mode', 'guided');
    Http::assertSentCount(3);
});

test('the kill switch and missing credentials start the guided questionnaire without calling the provider', function (bool $configured) {
    if ($configured) {
        enableAssistantAi();
        app(KillSwitches::class)->set(KillSwitchKey::AiAssistant, true, User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create());
    }
    Http::fake();

    $this->postJson('/en/assistant/sessions', [StartAssistantRequest::TIMESTAMP => StartAssistantRequest::timestampToken()])
        ->assertCreated()
        ->assertJsonPath('mode', 'guided')
        ->assertJsonPath('step', 'project')
        ->assertJsonPath('notice', 'Our AI assistant is not available right now, so we switched to a short guided questionnaire. Your answers still reach our team.');

    expect(ChatSession::sole()->fallback_reason)->toBe($configured ? 'disabled' : 'not_configured');
    Http::assertNothingSent();
})->with(['kill switch on' => true, 'not configured' => false]);

test('the guided questionnaire collects the same brief and still creates a lead', function () {
    Event::fake([LeadCreated::class]);
    [$session, $token] = startConversation();
    $answer = fn (array $data) => $this->withHeader('X-Assistant-Token', $token)
        ->postJson(route('assistant.answer', ['locale' => 'en', 'chatSession' => $session]), $data);

    $answer(['step' => 'project', 'project_type' => 'Clinic app', 'description' => 'Short'])->assertUnprocessable()->assertJsonValidationErrors('description');
    $answer(['step' => 'project', 'project_type' => 'Clinic app', 'description' => 'Booking for our three clinics.', 'services' => [$this->service->id]])
        ->assertSuccessful()->assertJsonPath('step', 'platforms');
    $answer(['step' => 'platforms', 'platforms' => ['smartwatch']])->assertUnprocessable()->assertJsonValidationErrors('platforms.0');
    $answer(['step' => 'platforms', 'platforms' => ['web', 'ios']])->assertSuccessful()->assertJsonPath('step', 'features');
    $answer(['step' => 'features', 'features' => "Booking\nReminders"])->assertSuccessful()->assertJsonPath('step', 'timeline');
    $answer(['step' => 'timeline', 'timeline' => 'flexible'])->assertSuccessful()->assertJsonPath('step', 'budget')->assertJsonPath('summary', null);

    $confirm = fn (array $data) => $this->withHeader('X-Assistant-Token', $token)
        ->postJson(route('assistant.confirm', ['locale' => 'en', 'chatSession' => $session]), $data);

    $answer(['step' => 'budget'])->assertSuccessful()->assertJsonPath('step', null)->assertJsonPath('summary.rows.0.value', 'Clinic app');

    $confirm(['name' => 'Ali', 'email' => 'ali@example.com', 'preferred_contact' => 'email', 'preferred_locale' => 'en'])
        ->assertUnprocessable()->assertJsonValidationErrors('consent');
    $confirm(['name' => 'Ali', 'email' => 'ali@example.com', 'preferred_contact' => 'phone', 'preferred_locale' => 'en', 'consent' => '1'])
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
    $confirm(['name' => 'Ali', 'email' => 'ali@example.com', 'preferred_contact' => 'email', 'preferred_locale' => 'en', 'consent' => '1'])
        ->assertSuccessful();

    $lead = Lead::sole();
    expect($lead->source)->toBe(LeadSource::AiAssistant)
        ->and($lead->platforms)->toBe(['web', 'ios'])
        ->and($lead->budget_range)->toBeNull()
        ->and($lead->brief['assistant']['mode'])->toBe('guided')
        ->and($lead->brief['assistant']['fallback_reason'])->toBe('not_configured')
        ->and($lead->chatSession->messages->pluck('content'))->toContain("Clinic app\nBooking for our three clinics.");

    Event::assertDispatched(LeadCreated::class);
    Notification::assertSentTo($this->sales, NewLeadReceived::class);
    Http::assertNothingSent();
});

test('a brief cannot be confirmed before the project details are known', function () {
    [$session, $token] = startConversation();

    $this->withHeader('X-Assistant-Token', $token)
        ->postJson(route('assistant.confirm', ['locale' => 'en', 'chatSession' => $session]), ['name' => 'Ali', 'email' => 'ali@example.com', 'preferred_contact' => 'email', 'preferred_locale' => 'en', 'consent' => '1'])
        ->assertUnprocessable();

    expect(Lead::count())->toBe(0);
});

test('a conversation only answers the browser holding its token', function () {
    $chat = ChatSession::factory()->create();

    $this->withHeader('X-Assistant-Token', 'wrong')
        ->postJson(route('assistant.message', ['locale' => 'en', 'chatSession' => $chat->ulid]), ['message' => 'Hi'])
        ->assertNotFound();
    $this->postJson(route('assistant.answer', ['locale' => 'en', 'chatSession' => $chat->ulid]), ['step' => 'platforms', 'platforms' => ['web']])
        ->assertNotFound();

    $old = ChatSession::factory()->create(['created_at' => now()->subDays(2)]);
    sendMessage($old->ulid, ChatSessionFactory::TOKEN, 'Hi')->assertStatus(410);
});

test('messages are length limited and conversations stop after the maximum number of turns', function () {
    enableAssistantAi();
    config(['codefanz.assistant.max_turns' => 1, 'codefanz.assistant.max_message_length' => 50]);
    Http::fake(['api.openai.com/*' => Http::response(completion(assistantTurn('Tell me more.')))]);

    [$session, $token] = startConversation();

    sendMessage($session, $token, str_repeat('a', 51))->assertUnprocessable()->assertJsonValidationErrors('message');
    sendMessage($session, $token, 'First message')->assertSuccessful();
    sendMessage($session, $token, 'Second message')->assertStatus(429)->assertJsonPath('limit_reached', true);

    Http::assertSentCount(1);
    expect(ChatSession::sole()->user_turns)->toBe(1);
});

test('messages are rate limited per conversation and new conversations per ip', function () {
    enableAssistantAi();
    config(['codefanz.assistant.messages_per_session_per_minute' => 2, 'codefanz.assistant.sessions_per_ip_per_hour' => 2]);
    Http::fake(['api.openai.com/*' => Http::response(completion(assistantTurn('Noted.')))]);

    [$session, $token] = startConversation();
    sendMessage($session, $token, 'One')->assertSuccessful();
    sendMessage($session, $token, 'Two')->assertSuccessful();
    sendMessage($session, $token, 'Three')->assertTooManyRequests();

    startConversation();
    $this->postJson('/en/assistant/sessions', [StartAssistantRequest::TIMESTAMP => StartAssistantRequest::timestampToken()])->assertTooManyRequests();
    Http::assertSentCount(2);
});

test('an arabic conversation is greeted in arabic and the model is told to reply in arabic', function () {
    enableAssistantAi();
    Http::fake(['api.openai.com/*' => Http::response(completion(assistantTurn('رائع! ما المنصات التي تحتاجها؟', ['project_type' => 'منصة حجز'])))]);

    $start = $this->postJson('/ar/assistant/sessions', [StartAssistantRequest::TIMESTAMP => StartAssistantRequest::timestampToken()])->assertCreated();
    expect($start->json('messages.1.content'))->toBe('ماذا تريد أن تبني؟');

    sendMessage($start->json('session'), $start->json('token'), 'أريد منصة حجز لعياداتنا', 'ar')
        ->assertSuccessful()
        ->assertJsonPath('messages.1.content', 'رائع! ما المنصات التي تحتاجها؟')
        ->assertJsonPath('progress.items.0.label', 'نوع المشروع');

    expect(ChatSession::sole()->locale)->toBe('ar');
    Http::assertSent(fn (Request $request): bool => str_contains($request['messages'][0]['content'], 'Reply in Arabic')
        && str_contains($request['messages'][0]['content'], $this->service->id.': تطبيقات الجوال'));
});

test('old conversations are pruned and converted ones are kept for the lead retention period', function () {
    config(['codefanz.assistant.retention_days' => 30, 'codefanz.assistant.lead_retention_days' => 365]);
    $lead = Lead::factory()->create(['source' => LeadSource::AiAssistant]);

    $stale = ChatSession::factory()->create(['created_at' => now()->subDays(31)]);
    $stale->messages()->create(['role' => ChatRole::User, 'content' => 'Old', 'created_at' => now()->subDays(31)]);
    $recent = ChatSession::factory()->create(['created_at' => now()->subDays(5)]);
    $converted = ChatSession::factory()->create(['created_at' => now()->subDays(200), 'lead_id' => $lead->id]);
    $expiredConverted = ChatSession::factory()->create(['created_at' => now()->subDays(400), 'lead_id' => Lead::factory()->create()->id]);

    $this->artisan('model:prune', ['--model' => [ChatSession::class]])->assertSuccessful();

    expect(ChatSession::query()->pluck('id')->sort()->values()->all())->toBe(collect([$recent->id, $converted->id])->sort()->values()->all())
        ->and(ChatMessage::query()->where('chat_session_id', $stale->id)->exists())->toBeFalse()
        ->and(Lead::query()->whereKey($lead->id)->exists())->toBeTrue();

    expect($expiredConverted->fresh())->toBeNull();
});

test('the contact page links the ai assistant channel', function () {
    $this->get('/ar/contact')->assertSuccessful()->assertSee('href="'.route('assistant', ['locale' => 'ar']).'"', false);
});
