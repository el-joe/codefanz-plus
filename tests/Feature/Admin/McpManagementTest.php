<?php

use App\Actions\Mcp\ManageMcpToken;
use App\Enums\McpCallResult;
use App\Enums\McpScope;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\McpClient;
use App\Models\McpToolCall;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->admin = User::factory()->role(Role::SuperAdmin)->withConfirmedTwoFactor()->create(['name' => 'Mona Adel']);
});

/**
 * @return array<string, mixed>
 */
function mcpClientPayload(User $owner, array $overrides = []): array
{
    return [
        'name' => 'claude-desktop',
        'description' => 'Content team laptop',
        'owner_id' => $owner->id,
        'status' => 'active',
        'scopes' => [McpScope::ContentRead->value, McpScope::ContentDraft->value],
        'allowed_ips' => "203.0.113.10\n10.0.0.0/8",
        'rate_limit_per_minute' => 30,
        'allow_lead_pii' => '0',
        'expires_at' => now()->addMonth()->format('Y-m-d'),
        ...$overrides,
    ];
}

test('only users with mcp.manage can open MCP management', function () {
    $contentManager = User::factory()->role(Role::ContentManager)->create();

    $this->actingAs($contentManager)->get(route('admin.mcp.index'))->assertForbidden();
    $this->actingAs($contentManager)->post(route('admin.mcp.clients.store'), mcpClientPayload($contentManager))->assertForbidden();

    expect(McpClient::count())->toBe(0);
});

test('the B08 screen lists clients, safety controls and the activity log in English and Arabic', function () {
    $client = McpClient::factory()->scopes([McpScope::ContentRead, McpScope::SeoPropose])->create(['name' => 'claude-code']);
    McpToolCall::create(['mcp_client_id' => $client->id, 'tool' => 'propose_seo_meta', 'method' => 'POST', 'result' => McpCallResult::Conflict, 'http_status' => 409, 'error_code' => 'stale_version', 'duration_ms' => 120, 'created_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.mcp.index'))
        ->assertOk()
        ->assertSee('claude-code')
        ->assertSee('seo:propose')
        ->assertSee('Safety controls')
        ->assertSee('All MCP access')
        ->assertSee('propose_seo_meta')
        ->assertSee('stale_version')
        ->assertSee('claude mcp add codefanz', false);

    $this->admin->update(['locale' => 'ar']);

    $this->actingAs($this->admin->fresh())->get(route('admin.mcp.index'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('ضوابط الأمان');
});

test('the activity log can be filtered by client and result', function () {
    $first = McpClient::factory()->create(['name' => 'alpha-client']);
    $second = McpClient::factory()->create(['name' => 'beta-client']);
    McpToolCall::create(['mcp_client_id' => $first->id, 'tool' => 'list_content', 'method' => 'GET', 'result' => McpCallResult::Ok, 'http_status' => 200, 'duration_ms' => 5, 'created_at' => now()]);
    McpToolCall::create(['mcp_client_id' => $second->id, 'tool' => 'create_lead', 'method' => 'POST', 'result' => McpCallResult::Denied, 'http_status' => 403, 'duration_ms' => 5, 'created_at' => now()]);

    $this->actingAs($this->admin)->get(route('admin.mcp.index', ['result' => 'denied']))
        ->assertOk()
        ->assertSee('create_lead')
        ->assertDontSee('<td class="px-4 py-3 font-mono text-xs text-fg" dir="ltr">list_content</td>', false);
});

test('creating a client issues a token that is shown exactly once and stored hashed', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.mcp.clients.store'), mcpClientPayload($this->admin));

    $client = McpClient::sole();
    $response->assertRedirect(route('admin.mcp.clients.edit', $client))->assertSessionHas('mcp_token');

    $plain = session('mcp_token');
    [$id, $secret] = explode('|', $plain, 2);
    $stored = PersonalAccessToken::findOrFail($id);

    expect($client->scopes)->toBe(['content:read', 'content:draft'])
        ->and($client->allowed_ips)->toBe(['203.0.113.10', '10.0.0.0/8'])
        ->and($client->rate_limit_per_minute)->toBe(30)
        ->and($stored->token)->toBe(hash('sha256', $secret))
        ->and($stored->abilities)->toBe(['content:read', 'content:draft'])
        ->and($stored->expires_at?->toDateString())->toBe($client->expires_at->toDateString());

    $this->get(route('admin.mcp.clients.edit', $client))->assertOk()->assertSee($plain)->assertSee('Copy token');
    $this->get(route('admin.mcp.clients.edit', $client))->assertOk()->assertDontSee($plain);

    expect(AuditLog::where('operation', 'mcp_client.created')->exists())->toBeTrue()
        ->and(AuditLog::where('operation', 'mcp_client.token_issued')->exists())->toBeTrue()
        ->and(AuditLog::query()->get()->contains(fn (AuditLog $log): bool => str_contains(json_encode([$log->before, $log->after]), $secret)))->toBeFalse();
});

test('scopes the owner does not hold and invalid IP ranges are rejected', function () {
    $developer = User::factory()->role(Role::Developer)->create();

    $this->actingAs($this->admin)->post(route('admin.mcp.clients.store'), mcpClientPayload($developer, ['scopes' => [McpScope::LeadsRead->value]]))
        ->assertSessionHasErrors('scopes');
    $this->actingAs($this->admin)->post(route('admin.mcp.clients.store'), mcpClientPayload($this->admin, ['allowed_ips' => '999.1.1.1/40']))
        ->assertSessionHasErrors('allowed_ips');
    $this->actingAs($this->admin)->post(route('admin.mcp.clients.store'), mcpClientPayload($this->admin, ['scopes' => ['users:manage']]))
        ->assertSessionHasErrors('scopes.0');

    expect(McpClient::count())->toBe(0);
});

test('updating scopes and expiry also updates the existing token', function () {
    $client = McpClient::factory()->allScopes()->create(['owner_id' => $this->admin->id]);
    app(ManageMcpToken::class)->issue($client, $this->admin);

    $this->actingAs($this->admin)->put(route('admin.mcp.clients.update', $client), mcpClientPayload($this->admin, ['scopes' => [McpScope::SeoRead->value], 'status' => 'disabled']))
        ->assertRedirect(route('admin.mcp.clients.edit', $client));

    $client->refresh();

    expect($client->scopes)->toBe(['seo:read'])
        ->and($client->status->value)->toBe('disabled')
        ->and($client->tokens()->sole()->abilities)->toBe(['seo:read'])
        ->and(AuditLog::where('operation', 'mcp_client.updated')->exists())->toBeTrue();
});

test('rotating replaces the token and revoking removes it', function () {
    $client = McpClient::factory()->create(['owner_id' => $this->admin->id]);
    $old = app(ManageMcpToken::class)->issue($client, $this->admin);

    $this->actingAs($this->admin)->post(route('admin.mcp.clients.token.store', $client))->assertSessionHas('mcp_token');

    expect($client->tokens()->count())->toBe(1)
        ->and(PersonalAccessToken::findToken($old))->toBeNull()
        ->and(AuditLog::where('operation', 'mcp_client.token_rotated')->exists())->toBeTrue();

    $this->actingAs($this->admin)->delete(route('admin.mcp.clients.token.destroy', $client))->assertRedirect();

    expect($client->tokens()->count())->toBe(0)
        ->and(AuditLog::where('operation', 'mcp_client.token_revoked')->exists())->toBeTrue();

    $this->get(route('admin.mcp.clients.edit', $client))->assertOk()->assertSee('Issue token');
});

test('deleting a client revokes its token', function () {
    $client = McpClient::factory()->create(['owner_id' => $this->admin->id]);
    app(ManageMcpToken::class)->issue($client, $this->admin);

    $this->actingAs($this->admin)->delete(route('admin.mcp.clients.destroy', $client))->assertRedirect(route('admin.mcp.index'));

    expect(McpClient::count())->toBe(0)
        ->and(PersonalAccessToken::count())->toBe(0)
        ->and(AuditLog::where('operation', 'mcp_client.deleted')->exists())->toBeTrue();
});
