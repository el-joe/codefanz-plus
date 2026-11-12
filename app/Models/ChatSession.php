<?php

namespace App\Models;

use App\Enums\ChatMode;
use App\Enums\ChatSessionStatus;
use Database\Factories\ChatSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AI Project Assistant conversation. The browser that started it holds a random token (only its hash is
 * stored); conversations are pruned after codefanz.assistant.retention_days, or lead_retention_days once
 * they became a lead (the lead keeps its structured brief either way).
 */
#[Fillable([
    'token_hash', 'locale', 'mode', 'status', 'fallback_reason', 'brief', 'user_turns', 'ip_hash', 'user_agent',
    'lead_id', 'last_activity_at', 'completed_at',
])]
class ChatSession extends Model
{
    /** @use HasFactory<ChatSessionFactory> */
    use HasFactory, HasUlids, MassPrunable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'mode' => 'ai',
        'status' => 'active',
        'user_turns' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['token_hash', 'ip_hash'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => ChatMode::class,
            'status' => ChatSessionStatus::class,
            'brief' => 'array',
            'user_turns' => 'integer',
            'last_activity_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<ChatSession>
     */
    public function prunable(): Builder
    {
        return static::query()->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->whereNull('lead_id')
                ->where('created_at', '<', now()->subDays((int) config('codefanz.assistant.retention_days'))))
            ->orWhere(fn (Builder $query) => $query->whereNotNull('lead_id')
                ->where('created_at', '<', now()->subDays((int) config('codefanz.assistant.lead_retention_days')))));
    }

    public function matchesToken(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->token_hash, hash('sha256', $token));
    }

    public function isOpen(): bool
    {
        return $this->status === ChatSessionStatus::Active
            && $this->created_at !== null
            && $this->created_at->gt(now()->subHours((int) config('codefanz.assistant.session_hours')));
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
