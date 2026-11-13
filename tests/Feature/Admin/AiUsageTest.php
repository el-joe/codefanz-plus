<?php

use App\Enums\AiFeature;
use App\Enums\ChatRole;
use App\Enums\LeadSource;
use App\Enums\Role;
use App\Models\AiUsageLog;
use App\Models\ChatSession;
use App\Models\Lead;
use App\Models\User;
use App\Services\Settings;

beforeEach(function () {
    $this->admin = User::factory()->role(Role::Administrator)->withConfirmedTwoFactor()->create();
});

test('the ai usage page shows monthly tokens, cost per feature and the budget status', function () {
    app(Settings::class)->set('integrations', 'ai_monthly_budget', '10');
    AiUsageLog::factory()->count(2)->create(['input_tokens' => 1500, 'output_tokens' => 500, 'estimated_cost_minor' => 450]);
    AiUsageLog::factory()->failed()->create(['error' => 'HTTP 503']);
    AiUsageLog::factory()->create(['feature' => AiFeature::ContentStudio, 'estimated_cost_minor' => 25, 'created_at' => now()->subMonthNoOverflow()]);

    $this->actingAs($this->admin)->get(route('admin.ai-usage'))
        ->assertSuccessful()
        ->assertSee('AI Project Assistant')
        ->assertSee('3,000')
        ->assertSee('$9.00 / $10.00')
        ->assertSee('data-ai-budget-status="near"', false)
        ->assertSee('HTTP 503')
        ->assertDontSee('AI Content Studio');

    $this->actingAs($this->admin)->get(route('admin.ai-usage', ['month' => now()->subMonthNoOverflow()->format('Y-m')]))
        ->assertSuccessful()
        ->assertSee('AI Content Studio')
        ->assertSee('$0.25');

    AiUsageLog::factory()->create(['estimated_cost_minor' => 200]);
    $this->actingAs($this->admin)->get(route('admin.ai-usage'))->assertSee('data-ai-budget-status="exceeded"', false);
});

test('only settings managers can see ai usage', function () {
    foreach ([Role::ContentManager, Role::SalesManager] as $role) {
        $this->actingAs(User::factory()->role($role)->withConfirmedTwoFactor()->create())->get(route('admin.ai-usage'))->assertForbidden();
    }
});

test('the ai provider, models and budget are configured in integrations with validation', function () {
    $this->actingAs($this->admin)->get(route('admin.settings.edit', 'integrations'))
        ->assertSuccessful()
        ->assertSee('OpenAI (AI features)')
        ->assertSee('data-ai-provider-selected', false)
        ->assertSee('name="settings[ai_provider]"', false);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'integrations'), ['settings' => ['ai_provider' => 'skynet', 'ai_monthly_budget' => '-5', 'openai_model' => 'bad model!']])
        ->assertSessionHasErrors(['settings.ai_provider', 'settings.ai_monthly_budget', 'settings.openai_model']);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update', 'integrations'), ['settings' => ['ai_provider' => 'anthropic', 'anthropic_model' => 'claude-sonnet-5-5', 'ai_monthly_budget' => '25', 'openai_api_key' => 'sk-secret']])
        ->assertSessionHasNoErrors();

    $settings = app(Settings::class);
    expect($settings->get('integrations', 'ai_provider'))->toBe('anthropic')
        ->and($settings->get('integrations', 'ai_monthly_budget'))->toBe('25')
        ->and($settings->get('integrations', 'openai_api_key'))->toBe('sk-secret');
});

test('assistant leads show the read-only, escaped transcript to lead viewers only', function () {
    $lead = Lead::factory()->create(['source' => LeadSource::AiAssistant]);
    $chat = ChatSession::factory()->guided()->create(['lead_id' => $lead->id]);
    $chat->messages()->create(['role' => ChatRole::Assistant, 'content' => 'What would you like to build?', 'created_at' => now()]);
    $chat->messages()->create(['role' => ChatRole::User, 'content' => '<script>alert(1)</script> A clinic app', 'created_at' => now()]);

    $viewer = User::factory()->role(Role::ProjectManager)->create();

    $this->actingAs($viewer)->get(route('admin.leads.show', $lead))
        ->assertSuccessful()
        ->assertSee('data-lead-transcript', false)
        ->assertSee('What would you like to build?')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; A clinic app', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('Guided questionnaire');

    $this->actingAs(User::factory()->role(Role::ContentManager)->withConfirmedTwoFactor()->create())->get(route('admin.leads.show', $lead))->assertForbidden();
});

test('pruned assistant transcripts are reported honestly on the lead', function () {
    $lead = Lead::factory()->create(['source' => LeadSource::AiAssistant]);

    $this->actingAs($this->admin)->get(route('admin.leads.show', $lead))
        ->assertSuccessful()
        ->assertSee('The conversation is no longer stored (retention period ended).');
});
