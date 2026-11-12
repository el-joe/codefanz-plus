<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A new project message. Sent to the other side of the conversation (client users or the project team) in the
 * recipient's own language; the mail links to the portal for clients and to the admin for staff.
 */
class NewConversationMessage extends Notification
{
    use Queueable;

    public function __construct(public Conversation $conversation, public ConversationMessage $message, public string $senderName, public bool $forClient) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message about :project', ['project' => $this->conversation->project->name]))
            ->line(__(':name wrote in “:thread”:', ['name' => $this->senderName, 'thread' => $this->conversation->displaySubject()]))
            ->line(Str::limit($this->message->body, 300))
            ->action(__('Open conversation'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'conversation.message',
            'conversation' => $this->conversation->ulid,
            'project' => $this->conversation->project->ulid,
            'project_name' => $this->conversation->project->name,
            'name' => $this->senderName,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->forClient
            ? route('portal.conversations.show', $this->conversation)
            : route('admin.projects.show', ['project' => $this->conversation->project, 'tab' => 'conversation', 'conversation' => $this->conversation->ulid]);
    }
}
