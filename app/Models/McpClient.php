<?php

namespace App\Models;

use App\Enums\McpClientStatus;
use App\Enums\McpScope;
use Database\Factories\McpClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;

/**
 * An MCP client (Claude Code, Claude Desktop, …) allowed to call /api/v1/mcp. It owns exactly one Sanctum token
 * whose abilities equal its scopes; every call is also limited to what its owner may do.
 */
#[Fillable(['name', 'description', 'owner_id', 'status', 'scopes', 'allowed_ips', 'rate_limit_per_minute', 'allow_lead_pii', 'expires_at', 'created_by', 'updated_by'])]
#[Hidden(['tokens'])]
class McpClient extends Model
{
    /** @use HasFactory<McpClientFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    public const TOKEN_NAME = 'mcp';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'rate_limit_per_minute' => 60,
        'allow_lead_pii' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => McpClientStatus::class,
            'scopes' => 'array',
            'allowed_ips' => 'array',
            'rate_limit_per_minute' => 'integer',
            'allow_lead_pii' => 'boolean',
            'expires_at' => 'datetime',
            'token_issued_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<McpToolCall, $this>
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(McpToolCall::class);
    }

    public function hasScope(McpScope $scope): bool
    {
        return in_array($scope->value, $this->scopes ?? [], true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === McpClientStatus::Active && ! $this->isExpired();
    }

    /**
     * The label used for change requests and audit entries created through this client.
     */
    public function actorLabel(): string
    {
        return 'MCP · '.$this->name;
    }

    /**
     * The client resolved for the current API request (by the Sanctum token).
     */
    public static function current(): ?self
    {
        $user = Auth::guard('sanctum')->user();

        return $user instanceof self ? $user : null;
    }
}
