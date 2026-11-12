<?php

namespace App\Models;

use App\Enums\ChangeAction;
use App\Enums\ChangeOrigin;
use App\Enums\ChangeRequestStatus;
use App\Enums\Permission;
use Database\Factories\ChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['reference', 'origin', 'actor_label', 'requested_by', 'action', 'target_type', 'target_id', 'target_version', 'locale', 'before', 'proposed', 'summary', 'required_permission', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'applied_at', 'apply_result', 'expires_at'])]
class ChangeRequest extends Model
{
    /** @use HasFactory<ChangeRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => ChangeOrigin::class,
            'action' => ChangeAction::class,
            'status' => ChangeRequestStatus::class,
            'required_permission' => Permission::class,
            'before' => 'array',
            'proposed' => 'array',
            'apply_result' => 'array',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
