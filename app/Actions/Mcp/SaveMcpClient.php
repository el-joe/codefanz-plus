<?php

namespace App\Actions\Mcp;

use App\Models\McpClient;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an MCP client from validated admin input. The client's existing token always mirrors its
 * scopes and expiry, so narrowing scopes or shortening the expiry takes effect on the very next call.
 */
class SaveMcpClient
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, description?: ?string, owner_id: int, status: string, scopes: list<string>, allowed_ips?: list<string>|null, rate_limit_per_minute: int, allow_lead_pii?: bool, expires_at?: mixed}  $data
     */
    public function handle(?McpClient $client, array $data, User $editor): McpClient
    {
        return DB::transaction(function () use ($client, $data, $editor): McpClient {
            $creating = $client === null;
            $client ??= new McpClient(['created_by' => $editor->getKey()]);
            $before = $creating ? null : $this->snapshot($client);

            $client->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id' => $data['owner_id'],
                'status' => $data['status'],
                'scopes' => array_values(array_unique($data['scopes'])),
                'allowed_ips' => ($data['allowed_ips'] ?? []) ?: null,
                'rate_limit_per_minute' => $data['rate_limit_per_minute'],
                'allow_lead_pii' => (bool) ($data['allow_lead_pii'] ?? false),
                'expires_at' => $data['expires_at'] ?? null,
                'updated_by' => $editor->getKey(),
            ])->save();

            $client->tokens()->update([
                'abilities' => json_encode($client->scopes),
                'expires_at' => $client->expires_at,
            ]);

            $this->audit->record(
                operation: $creating ? 'mcp_client.created' : 'mcp_client.updated',
                auditable: $client,
                user: $editor,
                before: $before,
                after: $this->snapshot($client),
            );

            return $client;
        });
    }

    public function delete(McpClient $client, User $editor): void
    {
        DB::transaction(function () use ($client, $editor): void {
            $client->tokens()->delete();
            $client->delete();

            $this->audit->record(operation: 'mcp_client.deleted', auditable: $client, user: $editor, before: $this->snapshot($client));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(McpClient $client): array
    {
        return [
            'name' => $client->name,
            'owner_id' => $client->owner_id,
            'status' => $client->status->value,
            'scopes' => $client->scopes,
            'allowed_ips' => $client->allowed_ips,
            'rate_limit_per_minute' => $client->rate_limit_per_minute,
            'allow_lead_pii' => $client->allow_lead_pii,
            'expires_at' => $client->expires_at?->toIso8601String(),
        ];
    }
}
