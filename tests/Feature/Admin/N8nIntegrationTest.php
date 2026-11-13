<?php

use App\Actions\Leads\ChangeLeadStatus;
use App\Actions\Leads\CreateLead;
use App\Enums\AutomationAction;
use App\Enums\AutomationDirection;
use App\Enums\AutomationRunStatus;
use App\Enums\KillSwitchKey;
use App\Enums\LeadActivityType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\RadarPlatformStatus;
use App\Enums\Role;
use App\Events\RadarProjectScored;
use App\Jobs\DeliverAutomationWebhook;
use App\Models\AuditLog;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Automation\AutomationDelivery;
use App\Services\Automation\WebhookSigner;
use App\Services\KillSwitches;
use Database\Seeders\RadarPlatformSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create(['name' => 'Omar Admin']);
    $this->sales = User::factory()->role(Role::SalesManager)->create(['name' => 'Mona Adel']);
});

function signedInbound(TestCase $test, AutomationWorkflow $workflow, array $payload, ?string $delivery = null, ?int $timestamp = null, ?string $secret = null, ?string $signature = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp ??= now()->getTimestamp();
    $signature ??= app(WebhookSigner::class)->sign($secret ?? (string) $workflow->secret, $timestamp, $body);

    return $test->call('POST', route('webhooks.n8n', $workflow->key), server: [
        'HTTP_X_CODEFANZ_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_CODEFANZ_SIGNATURE' => $signature,
        'HTTP_X_CODEFANZ_DELIVERY' => $delivery ?? 'n8n-exec-'.uniqid(),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], content: $body);
}

function leadPayload(array $overrides = []): array
{
    return ['name' => 'Layla Hassan', 'email' => 'layla@example.test', 'preferred_locale' => 'ar', 'description' => 'Need an ERP', ...$overrides];
}

// --- Outbound -----------------------------------------------------------------------------------------------------

test('domain events are delivered to subscribed workflows as signed webhooks', function () {
    Http::fake(['n8n.example.test/*' => Http::response(['ok' => true])]);
    $workflow = AutomationWorkflow::factory()->create(['events' => ['lead.created', 'lead.status_changed']]);
    AutomationWorkflow::factory()->create(['events' => ['payment.succeeded']]);
    AutomationWorkflow::factory()->create(['events' => ['lead.created'], 'is_enabled' => false]);

    $lead = app(CreateLead::class)->handle(leadPayload(), LeadSource::Manual, creator: $this->sales);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($workflow, $lead): bool {
        $timestamp = $request->header('X-CodeFanz-Timestamp')[0];
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'whsec_test_secret_value');

        return $request->url() === $workflow->url
            && $request->header('X-CodeFanz-Signature')[0] === $expected
            && $request->header('X-CodeFanz-Event')[0] === 'lead.created'
            && $request['event'] === 'lead.created'
            && $request['id'] === $request->header('X-CodeFanz-Delivery')[0]
            && $request['data']['lead']['reference'] === $lead->reference
            && ! array_key_exists('email', $request['data']['lead']);
    });

    $run = AutomationRun::query()->sole();
    expect($run->status)->toBe(AutomationRunStatus::Succeeded)
        ->and($run->attempts)->toBe(1)
        ->and($run->http_status)->toBe(200)
        ->and($workflow->fresh()->last_status)->toBe(AutomationRunStatus::Succeeded);

    app(ChangeLeadStatus::class)->handle($lead->fresh(), LeadStatus::Contacted, null, $lead->fresh()->lock_version, $this->sales);
    Http::assertSent(fn (Request $request): bool => $request['event'] === 'lead.status_changed' && $request['data']['to'] === 'contacted');
});

test('personal data is only sent to workflows that allow it', function () {
    Http::fake(['n8n.example.test/*' => Http::response('ok')]);
    AutomationWorkflow::factory()->create(['include_personal_data' => true]);

    app(CreateLead::class)->handle(leadPayload(), LeadSource::Manual, creator: $this->sales);

    Http::assertSent(fn (Request $request): bool => $request['data']['lead']['email'] === 'layla@example.test');
});

test('failed deliveries are retried with backoff, logged, then marked failed and can be retried by hand', function () {
    config(['codefanz.automation.tries' => 3]);
    Http::fake(['n8n.example.test/*' => Http::sequence()->push('down', 500)->push('down', 502)->push('down', 503)->push(['ok' => true])]);
    $workflow = AutomationWorkflow::factory()->create(['events' => ['radar.project_scored']]);
    $this->seed(RadarPlatformSeeder::class);

    event(new RadarProjectScored(RadarProject::factory()->create()));

    $run = AutomationRun::query()->sole();
    expect($run->status)->toBe(AutomationRunStatus::Pending)->and($run->attempts)->toBe(1)->and($run->error)->toBe('HTTP 500');

    (new DeliverAutomationWebhook($run->id))->handle(app(AutomationDelivery::class));
    (new DeliverAutomationWebhook($run->id))->handle(app(AutomationDelivery::class));

    $run->refresh();
    expect($run->status)->toBe(AutomationRunStatus::Failed)
        ->and($run->attempts)->toBe(3)
        ->and($run->http_status)->toBe(503)
        ->and($workflow->fresh()->last_status)->toBe(AutomationRunStatus::Failed);

    // Every attempt resends the same body with the same delivery id (the receiver's idempotency key).
    $bodies = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->body())->unique();
    $ids = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->header('X-CodeFanz-Delivery')[0])->unique();
    expect($bodies)->toHaveCount(1)->and($ids->all())->toBe([$run->uuid]);

    $this->actingAs($this->admin)->get(route('admin.automation.index'))->assertSuccessful()->assertSee('HTTP 503')->assertSee(__('Retry'));
    $this->actingAs($this->admin)->post(route('admin.automation.runs.retry', $run))->assertRedirect();

    expect($run->fresh()->status)->toBe(AutomationRunStatus::Succeeded);
});

test('the backoff schedule grows between attempts', function () {
    $delivery = app(AutomationDelivery::class);

    expect($delivery->backoffSeconds(1))->toBe(60)
        ->and($delivery->backoffSeconds(2))->toBe(300)
        ->and($delivery->backoffSeconds(9))->toBe(3600);
});

test('the n8n kill switch stops outbound deliveries', function () {
    Http::fake();
    AutomationWorkflow::factory()->create();
    app(KillSwitches::class)->set(KillSwitchKey::AutomationWebhooks, true, $this->admin);

    app(CreateLead::class)->handle(leadPayload(), LeadSource::Manual, creator: $this->sales);

    Http::assertNothingSent();
    expect(AutomationRun::query()->sole()->status)->toBe(AutomationRunStatus::Failed)
        ->and(AutomationRun::query()->sole()->error)->toBe('Paused by an emergency control.');
});

test('a test event can be sent from the admin', function () {
    Http::fake(['n8n.example.test/*' => Http::response('ok')]);
    $workflow = AutomationWorkflow::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.automation.test', $workflow))->assertRedirect();

    Http::assertSent(fn (Request $request): bool => $request['event'] === 'test.ping');
    expect(AutomationRun::query()->sole()->status)->toBe(AutomationRunStatus::Succeeded);
});

// --- Inbound ------------------------------------------------------------------------------------------------------

test('a signed inbound request runs the endpoint action as its acting user', function () {
    Queue::fake();
    $workflow = AutomationWorkflow::factory()->inbound(AutomationAction::CreateLead, $this->sales)->create();

    signedInbound($this, $workflow, leadPayload(), 'exec-1')
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    $lead = Lead::query()->sole();
    expect($lead->source)->toBe(LeadSource::Automation)
        ->and($lead->created_by)->toBe($this->sales->id)
        ->and(AutomationRun::query()->sole()->status)->toBe(AutomationRunStatus::Succeeded)
        ->and(AutomationRun::query()->sole()->payload)->toBeNull()
        ->and(AuditLog::query()->where('operation', 'automation.inbound.create_lead')->exists())->toBeTrue();
});

test('inbound requests with a bad signature, a stale timestamp or a reused delivery id are refused', function () {
    $workflow = AutomationWorkflow::factory()->inbound(AutomationAction::CreateLead, $this->sales)->create();

    signedInbound($this, $workflow, leadPayload(), secret: 'whsec_wrong')->assertStatus(401)->assertJsonPath('error', 'invalid_signature');
    signedInbound($this, $workflow, leadPayload(), signature: 'sha256=deadbeef')->assertStatus(401);
    signedInbound($this, $workflow, leadPayload(), timestamp: now()->subMinutes(10)->getTimestamp())->assertStatus(401);
    signedInbound($this, $workflow, leadPayload(), delivery: '')->assertStatus(400);

    expect(Lead::query()->count())->toBe(0)->and(AutomationRun::query()->count())->toBe(0);

    signedInbound($this, $workflow, leadPayload(), 'exec-42')->assertOk();
    signedInbound($this, $workflow, leadPayload(), 'exec-42')->assertStatus(409)->assertJsonPath('error', 'replayed');

    expect(Lead::query()->count())->toBe(1);
});

test('disabled, unknown and paused endpoints refuse requests', function () {
    $workflow = AutomationWorkflow::factory()->inbound(AutomationAction::CreateLead, $this->sales)->create(['is_enabled' => false]);

    signedInbound($this, $workflow, leadPayload())->assertForbidden();
    $this->postJson('/webhooks/n8n/unknown-endpoint', leadPayload())->assertNotFound();

    $workflow->update(['is_enabled' => true]);
    app(KillSwitches::class)->set(KillSwitchKey::AutomationWebhooks, true, $this->admin);
    signedInbound($this, $workflow, leadPayload())->assertStatus(503);

    $outbound = AutomationWorkflow::factory()->create();
    $this->postJson('/webhooks/n8n/'.$outbound->key, [])->assertNotFound();

    expect(Lead::query()->count())->toBe(0);
});

test('inbound endpoints only run their whitelisted action, validated, with the acting user permission', function () {
    $workflow = AutomationWorkflow::factory()->inbound(AutomationAction::AddLeadNote, $this->sales)->create();
    $lead = app(CreateLead::class)->handle(leadPayload(), LeadSource::Manual, creator: $this->sales, notify: false);

    // The body cannot pick another action: the lead fields are ignored and the note action needs its own fields.
    signedInbound($this, $workflow, ['action' => 'create_lead', ...leadPayload()])->assertStatus(422)->assertJsonPath('error', 'validation_failed');
    expect(Lead::query()->count())->toBe(1);

    signedInbound($this, $workflow, ['lead_reference' => $lead->reference, 'body' => 'Called back via n8n.'])->assertOk();
    expect($lead->activities()->where('type', LeadActivityType::Note)->sole()->body)->toBe('Called back via n8n.');

    // The acting user loses the permission: refused.
    $support = User::factory()->role(Role::SupportAgent)->create();
    $workflow->update(['acts_as_user_id' => $support->id]);
    signedInbound($this, $workflow->fresh(), ['lead_reference' => $lead->reference, 'body' => 'x'])->assertForbidden();

    expect(AutomationRun::query()->where('status', AutomationRunStatus::Rejected)->count())->toBe(2);
});

test('an inbound endpoint can open a support ticket for a customer', function () {
    $agent = User::factory()->role(Role::SupportAgent)->create();
    $customer = Customer::factory()->create();
    $workflow = AutomationWorkflow::factory()->inbound(AutomationAction::CreateTicket, $agent)->create();
    Http::fake(['n8n.example.test/*' => Http::response('ok')]);
    AutomationWorkflow::factory()->create(['events' => ['ticket.opened']]);

    signedInbound($this, $workflow, ['customer_reference' => $customer->reference, 'subject' => 'Login broken', 'body' => 'Email from the client.'])->assertOk();

    $ticket = SupportTicket::query()->sole();
    expect($ticket->customer_id)->toBe($customer->id)->and($ticket->opened_by)->toBe($agent->id);
    Http::assertSent(fn (Request $request): bool => $request['event'] === 'ticket.opened' && $request['data']['ticket']['reference'] === $ticket->reference && ! isset($request['data']['ticket']['subject']));
});

test('a Mostaql alert email forwarded by n8n is imported and activates the email connector', function () {
    $this->seed(RadarPlatformSeeder::class);
    expect(RadarPlatform::query()->where('key', 'mostaql')->sole()->status)->toBe(RadarPlatformStatus::NeedsCredentials);

    $this->actingAs($this->admin)->post(route('admin.automation.store'), [
        'name' => 'Radar: collect Mostaql email alerts', 'direction' => 'inbound', 'action' => 'radar_email', 'acts_as_user_id' => $this->sales->id, 'is_enabled' => '1',
    ])->assertRedirect();

    $workflow = AutomationWorkflow::query()->sole();
    expect(RadarPlatform::query()->where('key', 'mostaql')->sole()->status)->toBe(RadarPlatformStatus::Active);

    $payload = ['from' => 'Mostaql <no-reply@mostaql.com>', 'subject' => 'مشاريع جديدة', 'html' => file_get_contents(base_path('tests/Fixtures/radar/mostaql-alert.html'))];

    signedInbound($this, $workflow, $payload, 'imap-1')->assertOk()->assertJsonPath('result.created', 2);
    signedInbound($this, $workflow, $payload, 'imap-2')->assertOk()->assertJsonPath('result.unchanged', 2);

    expect(RadarProject::query()->count())->toBe(2)
        ->and(RadarProject::query()->where('external_id', '812345')->sole()->language)->toBe('ar');

    signedInbound($this, $workflow, ['from' => 'someone@example.test', 'text' => 'hello'], 'imap-3')->assertStatus(422);
});

// --- Admin --------------------------------------------------------------------------------------------------------

test('administrators create workflows; the secret is shown once and never stored in plain text', function () {
    $this->actingAs($this->admin)->post(route('admin.automation.store'), [
        'name' => 'New lead → Telegram + email', 'direction' => 'outbound', 'url' => 'https://n8n.example.test/webhook/lead', 'events' => ['lead.created'], 'is_enabled' => '1',
    ])->assertRedirect()->assertSessionHas('automation_secret');

    $workflow = AutomationWorkflow::query()->sole();
    $raw = DB::table('automation_workflows')->value('secret');

    expect($workflow->direction)->toBe(AutomationDirection::Outbound)
        ->and($workflow->secret)->toStartWith('whsec_')
        ->and($raw)->not->toContain($workflow->secret)
        ->and(json_encode(AuditLog::query()->where('operation', 'automation.workflow_created')->sole()->after))->not->toContain($workflow->secret);

    $this->actingAs($this->admin)->post(route('admin.automation.store'), [
        'name' => 'Bad', 'direction' => 'outbound', 'url' => 'not-a-url', 'events' => ['nope'],
    ])->assertSessionHasErrors(['url', 'events.0']);

    $this->actingAs($this->admin)->post(route('admin.automation.store'), [
        'name' => 'Bad inbound', 'direction' => 'inbound', 'action' => 'run_sql', 'acts_as_user_id' => $this->sales->id,
    ])->assertSessionHasErrors('action');

    $support = User::factory()->role(Role::SupportAgent)->create();
    $this->actingAs($this->admin)->post(route('admin.automation.store'), [
        'name' => 'Leads by support', 'direction' => 'inbound', 'action' => 'create_lead', 'acts_as_user_id' => $support->id,
    ])->assertSessionHasErrors('acts_as_user_id');

    $old = $workflow->secret;
    $this->actingAs($this->admin)->post(route('admin.automation.rotate', $workflow))->assertSessionHas('automation_secret');
    expect($workflow->fresh()->secret)->not->toBe($old);

    $this->actingAs($this->admin)->post(route('admin.automation.toggle', $workflow))->assertRedirect();
    expect($workflow->fresh()->is_enabled)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.automation.destroy', $workflow))->assertRedirect(route('admin.automation.index'));
    expect(AutomationWorkflow::query()->count())->toBe(0);
});

test('n8n screens need automation.manage', function () {
    $workflow = AutomationWorkflow::factory()->create();

    $this->actingAs($this->sales)->get(route('admin.automation.index'))->assertForbidden();
    $this->actingAs($this->sales)->get(route('admin.automation.edit', $workflow))->assertForbidden();
    $this->actingAs($this->sales)->post(route('admin.automation.test', $workflow))->assertForbidden();
    $this->actingAs($this->sales)->post(route('admin.automation.store'), ['name' => 'x'])->assertForbidden();
});

test('n8n screens render in English and Arabic', function () {
    $outbound = AutomationWorkflow::factory()->create(['name' => 'Payment confirmed → notify PM']);
    $inbound = AutomationWorkflow::factory()->inbound(AutomationAction::RadarEmail, $this->sales)->create(['name' => 'Radar email']);

    $this->actingAs($this->admin)->get(route('admin.automation.index'))
        ->assertSuccessful()
        ->assertSee('Payment confirmed → notify PM')
        ->assertSee('Signed webhooks · HMAC-SHA256');
    $this->actingAs($this->admin)->get(route('admin.automation.edit', $inbound))->assertSuccessful()->assertSee(route('webhooks.n8n', $inbound->key));
    $this->actingAs($this->admin)->get(route('admin.automation.create', ['direction' => 'inbound']))->assertSuccessful();

    $this->admin->update(['locale' => 'ar']);

    foreach ([route('admin.automation.index'), route('admin.automation.edit', $outbound), route('admin.automation.create')] as $url) {
        $this->actingAs($this->admin)->get($url)->assertSuccessful()->assertSee('dir="rtl"', false);
    }

    $this->actingAs($this->admin)->get(route('admin.automation.index'))->assertSee('تكاملات n8n');
});
