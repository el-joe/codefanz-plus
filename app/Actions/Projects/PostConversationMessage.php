<?php

namespace App\Actions\Projects;

use App\Enums\ClientVisibility;
use App\Enums\DocumentCategory;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Notifications\NewConversationMessage;
use App\Services\AuditLogger;
use App\Services\DeliveryRecipients;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Posts a plain-text message (optionally with a file, stored as a client-visible project document), moves the
 * author's read marker, and notifies the other side: portal users for staff messages, the project team for
 * client messages. Each recipient gets the notification in their own language.
 */
class PostConversationMessage
{
    public function __construct(
        private AuditLogger $audit,
        private DeliveryRecipients $recipients,
        private StoreDocument $storeDocument,
    ) {}

    public function handle(Conversation $conversation, string $body, User $author, bool $fromClient, ?UploadedFile $attachment = null): ConversationMessage
    {
        $conversation->loadMissing('project');

        $message = DB::transaction(function () use ($conversation, $body, $author, $fromClient): ConversationMessage {
            $message = $conversation->messages()->create([
                'user_id' => $author->getKey(),
                'from_client' => $fromClient,
                'body' => trim($body),
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            $this->audit->record(
                operation: 'conversation.message_posted',
                auditable: $conversation,
                user: $author,
                after: ['message_id' => $message->getKey(), 'from_client' => $fromClient, 'length' => mb_strlen($message->body)],
            );

            return $message;
        });

        if ($attachment !== null) {
            $this->storeDocument->handle(
                $conversation->project,
                $attachment,
                null,
                DocumentCategory::Other,
                ClientVisibility::Client,
                $author,
                byClient: $fromClient,
                message: $message,
            );
        }

        $conversation->markReadBy($author);

        $recipients = $fromClient
            ? $this->recipients->projectTeam($conversation->project, except: $author)
            : $this->recipients->clientUsers($conversation->customer_id, except: $author);

        Notification::send($recipients, new NewConversationMessage($conversation, $message, $author->name, forClient: ! $fromClient));

        return $message;
    }
}
