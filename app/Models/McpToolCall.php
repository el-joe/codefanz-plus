<?php

namespace App\Models;

use App\Enums\McpCallResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to an MCP tool endpoint. Arguments are sanitized before they are stored (no tokens, no personal data).
 */
#[Fillable(['mcp_client_id', 'tool', 'method', 'arguments', 'result', 'http_status', 'error_code', 'duration_ms', 'change_request_id', 'ip_address', 'created_at'])]
class McpToolCall extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => McpCallResult::class,
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<McpClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(McpClient::class, 'mcp_client_id')->withTrashed();
    }

    /**
     * @return BelongsTo<ChangeRequest, $this>
     */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class);
    }

    /**
     * @return Builder<McpToolCall>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('codefanz.mcp.tool_call_retention_days')));
    }
}
