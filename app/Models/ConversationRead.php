<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-user read marker for a conversation (newest message id the user has seen).
 */
#[Fillable(['conversation_id', 'user_id', 'last_read_message_id', 'read_at'])]
class ConversationRead extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_message_id' => 'integer',
            'read_at' => 'datetime',
        ];
    }
}
