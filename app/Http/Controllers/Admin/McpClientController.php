<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Mcp\ManageMcpToken;
use App\Actions\Mcp\SaveMcpClient;
use App\Enums\KillSwitchKey;
use App\Enums\McpCallResult;
use App\Enums\McpClientStatus;
use App\Enums\McpScope;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMcpClientRequest;
use App\Models\McpClient;
use App\Models\McpToolCall;
use App\Models\User;
use App\Services\KillSwitches;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * B08 MCP Management (mcp.manage): clients with scopes, limits and IP allow-lists, one token per client (shown
 * once, rotate / revoke), the MCP kill switches and the tool-call log with filters. Every change is audited.
 */
class McpClientController extends Controller
{
    public function index(Request $request, KillSwitches $killSwitches): View
    {
        $filters = $request->validate([
            'client' => ['nullable', 'integer'],
            'tool' => ['nullable', 'string', 'max:80'],
            'result' => ['nullable', Rule::enum(McpCallResult::class)],
        ]);

        $calls = McpToolCall::query()
            ->with(['client', 'changeRequest'])
            ->when($filters['client'] ?? null, fn ($query, $client) => $query->where('mcp_client_id', $client))
            ->when($filters['tool'] ?? null, fn ($query, $tool) => $query->where('tool', $tool))
            ->when($filters['result'] ?? null, fn ($query, $result) => $query->where('result', $result))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $clients = McpClient::query()->with('owner')->withExists('tokens')->orderBy('name')->get();

        return view('admin.mcp.index', [
            'clients' => $clients,
            'calls' => $calls,
            'filters' => $filters,
            'tools' => McpToolCall::query()->distinct()->orderBy('tool')->pluck('tool', 'tool')->all(),
            'switches' => collect([KillSwitchKey::McpAccess, KillSwitchKey::McpWrites])->map(fn (KillSwitchKey $key): array => ['key' => $key, 'active' => $killSwitches->isActive($key)]),
            'apiUrl' => url('/api/v1/mcp'),
        ]);
    }

    public function create(): View
    {
        return view('admin.mcp.form', [
            'client' => new McpClient(['owner_id' => auth()->id(), 'scopes' => [McpScope::ContentRead->value]]),
            ...$this->formData(),
        ]);
    }

    public function store(SaveMcpClientRequest $request, SaveMcpClient $save, ManageMcpToken $tokens): RedirectResponse
    {
        $client = $save->handle(null, $request->clientData(), $request->user());
        $token = $tokens->issue($client, $request->user());

        return to_route('admin.mcp.clients.edit', $client)
            ->with('mcp_token', $token)
            ->with('toast', __('MCP client created. Copy the token now — it will not be shown again.'));
    }

    public function edit(McpClient $client): View
    {
        $client->load('owner')->loadExists('tokens');

        return view('admin.mcp.form', [
            'client' => $client,
            'token' => $client->tokens()->latest('id')->first(),
            'recentCalls' => $client->toolCalls()->with('changeRequest')->latest('id')->limit(10)->get(),
            ...$this->formData(),
        ]);
    }

    public function update(SaveMcpClientRequest $request, McpClient $client, SaveMcpClient $save): RedirectResponse
    {
        $save->handle($client, $request->clientData(), $request->user());

        return to_route('admin.mcp.clients.edit', $client)->with('toast', __('MCP client saved.'));
    }

    public function destroy(Request $request, McpClient $client, SaveMcpClient $save): RedirectResponse
    {
        $save->delete($client, $request->user());

        return to_route('admin.mcp.index')->with('toast', __('MCP client deleted and its token revoked.'));
    }

    public function issueToken(Request $request, McpClient $client, ManageMcpToken $tokens): RedirectResponse
    {
        $token = $tokens->issue($client, $request->user());

        return to_route('admin.mcp.clients.edit', $client)
            ->with('mcp_token', $token)
            ->with('toast', __('New token issued. The previous token no longer works.'));
    }

    public function revokeToken(Request $request, McpClient $client, ManageMcpToken $tokens): RedirectResponse
    {
        $tokens->revoke($client, $request->user());

        return to_route('admin.mcp.clients.edit', $client)->with('toast', __('Token revoked. The client can no longer connect.'));
    }

    /**
     * @return array{owners: Collection<int, string>, scopes: list<McpScope>, statuses: array<string, string>}
     */
    private function formData(): array
    {
        return [
            'owners' => User::query()->holdingPermission(Permission::AdminAccess)->orderBy('name')->pluck('name', 'id'),
            'scopes' => McpScope::cases(),
            'statuses' => McpClientStatus::options(),
        ];
    }
}
