<?php

namespace App\Actions\Mcp;

use App\Models\McpClient;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Issues, rotates and revokes an MCP client's single Sanctum token. The plain-text token is returned once for
 * display; only its SHA-256 hash is stored. The token value never reaches the audit log.
 */
class ManageMcpToken
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Revoke any existing token and issue a new one. Returns the plain-text token (shown once).
     */
    public function issue(McpClient $client, User $editor): string
    {
        return DB::transaction(function () use ($client, $editor): string {
            $rotated = $client->tokens()->exists();
            $client->tokens()->delete();

            $token = $client->createToken(McpClient::TOKEN_NAME, $client->scopes ?? [], $client->expires_at);
            $client->forceFill(['token_issued_at' => now()])->save();

            $this->audit->record(
                operation: $rotated ? 'mcp_client.token_rotated' : 'mcp_client.token_issued',
                auditable: $client,
                user: $editor,
                after: ['token_id' => $token->accessToken->getKey(), 'abilities' => $client->scopes, 'expires_at' => $client->expires_at?->toIso8601String()],
            );

            return $token->plainTextToken;
        });
    }

    public function revoke(McpClient $client, User $editor): void
    {
        DB::transaction(function () use ($client, $editor): void {
            $count = $client->tokens()->delete();
            $client->forceFill(['token_issued_at' => null])->save();

            $this->audit->record(operation: 'mcp_client.token_revoked', auditable: $client, user: $editor, after: ['revoked_tokens' => $count]);
        });
    }
}
