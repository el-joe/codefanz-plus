<?php

namespace App\Models;

use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plain-text message in a project conversation; optional files are project documents linked by conversation_message_id.
 */
#[Fillable(['conversation_id', 'user_id', 'from_client', 'body'])]
class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_client' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * JSON shape used by the thread view and its polling endpoint (plain text; the browser renders with textContent).
     * Requires `author` and `attachments` loaded. Internal attachments are dropped for portal viewers.
     *
     * @return array{id: int, mine: bool, from_client: bool, author: string, initials: string, body: string, time: string, date: string, attachments: list<array{name: string, size: string, url: string}>}
     */
    public function threadPayload(User $viewer, bool $forPortal): array
    {
        $attachments = $this->attachments
            ->when($forPortal, fn ($documents) => $documents->filter(fn (Document $document): bool => $document->isClientVisible()))
            ->map(fn (Document $document): array => [
                'name' => $document->original_name,
                'size' => $document->humanSize(),
                'url' => route($forPortal ? 'portal.documents.download' : 'admin.documents.download', $document),
            ])
            ->values()
            ->all();

        return [
            'id' => $this->getKey(),
            'mine' => $this->user_id === $viewer->getKey(),
            'from_client' => $this->from_client,
            'author' => $this->author?->name ?? ($this->from_client ? __('Former user') : __('CodeFanz team')),
            'initials' => $this->author?->initials() ?? ($this->from_client ? '?' : 'CF'),
            'body' => $this->body,
            'time' => $this->created_at->translatedFormat('H:i'),
            'date' => $this->created_at->isToday() ? __('Today') : $this->created_at->translatedFormat('j M Y'),
            'attachments' => $attachments,
        ];
    }
}
