<?php

namespace App\Models;

use App\Enums\AutomationDirection;
use App\Enums\AutomationRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery (outbound) or one received request (inbound). Outbound runs keep the exact JSON body so retries
 * send the same payload with the same delivery id (the receiver's idempotency key).
 */
#[Fillable(['uuid', 'automation_workflow_id', 'direction', 'event', 'idempotency_key', 'payload_hash', 'payload', 'status', 'attempts', 'http_status', 'response', 'error', 'duration_ms', 'ip_address', 'completed_at'])]
class AutomationRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => AutomationDirection::class,
            'status' => AutomationRunStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AutomationWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'automation_workflow_id')->withTrashed();
    }
}
