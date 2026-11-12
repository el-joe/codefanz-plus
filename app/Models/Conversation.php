<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A project message thread between the customer's portal users and the CodeFanz team.
 */
#[Fillable(['project_id', 'customer_id', 'subject', 'created_by', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use BelongsToCustomer, HasFactory, HasUlids;

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
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Thread label in the reader's language (the general thread has no stored subject).
     */
    public function displaySubject(): string
    {
        return $this->subject ?? __('General');
    }

    /**
     * Adds `unread_count` for the user: messages from other people after their last read marker.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeWithUnreadCountFor(Builder $query, User $user): void
    {
        $query->addSelect(['unread_count' => ConversationMessage::query()
            ->selectRaw('count(*)')
            ->whereColumn('conversation_messages.conversation_id', 'conversations.id')
            ->where(fn (Builder $query) => $query->whereNull('conversation_messages.user_id')->orWhere('conversation_messages.user_id', '!=', $user->getKey()))
            ->where('conversation_messages.id', '>', fn (QueryBuilder $query) => $query
                ->select(DB::raw('coalesce(max(conversation_reads.last_read_message_id), 0)'))
                ->from('conversation_reads')
                ->whereColumn('conversation_reads.conversation_id', 'conversations.id')
                ->where('conversation_reads.user_id', $user->getKey())),
        ]);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('id');
    }

    /**
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    /**
     * @return HasMany<ConversationRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }

    /**
     * Move the user's read marker to the newest message.
     */
    public function markReadBy(User $user): void
    {
        $latest = (int) $this->messages()->max('id');

        $read = ConversationRead::query()->firstOrNew(['conversation_id' => $this->getKey(), 'user_id' => $user->getKey()]);

        if ($read->exists && $read->last_read_message_id >= $latest) {
            return;
        }

        $read->fill(['last_read_message_id' => $latest, 'read_at' => now()])->save();
    }
}
