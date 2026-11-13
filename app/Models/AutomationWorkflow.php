<?php

namespace App\Models;

use App\Enums\AutomationAction;
use App\Enums\AutomationDirection;
use App\Enums\AutomationRunStatus;
use Database\Factories\AutomationWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An n8n integration: either an outbound workflow (we POST signed events to its webhook URL) or an inbound
 * endpoint (n8n POSTs signed requests to /webhooks/n8n/{key} to run one whitelisted action). Each has its own
 * HMAC secret, stored encrypted.
 */
#[Fillable(['key', 'name', 'direction', 'url', 'secret', 'events', 'action', 'acts_as_user_id', 'include_personal_data', 'is_enabled', 'last_run_at', 'last_status', 'created_by', 'updated_by'])]
#[Hidden(['secret'])]
class AutomationWorkflow extends Model
{
    /** @use HasFactory<AutomationWorkflowFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
        'include_personal_data' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => AutomationDirection::class,
            'action' => AutomationAction::class,
            'secret' => 'encrypted',
            'events' => 'array',
            'is_enabled' => 'boolean',
            'include_personal_data' => 'boolean',
            'last_run_at' => 'datetime',
            'last_status' => AutomationRunStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acts_as_user_id');
    }

    /**
     * @return HasMany<AutomationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function isOutbound(): bool
    {
        return $this->direction === AutomationDirection::Outbound;
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, (array) $this->events, true);
    }

    public function maskedSecret(): string
    {
        return '••••'.mb_substr((string) $this->secret, -4);
    }
}
