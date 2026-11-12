<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of an article after a save, restore or applied change request.
 */
#[Fillable(['post_id', 'user_id', 'actor_label', 'reason', 'restored_from_id', 'lock_version', 'snapshot'])]
class PostRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reasonLabel(): string
    {
        return match ($this->reason) {
            'created' => __('Created'),
            'restored' => __('Restored'),
            'change_request' => __('Approved change request'),
            default => __('Saved'),
        };
    }
}
