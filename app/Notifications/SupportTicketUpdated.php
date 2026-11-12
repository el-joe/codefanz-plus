<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ticket activity for the other side: a new ticket or client reply for support staff, a staff reply or status
 * change for the client. Never includes internal notes.
 */
class SupportTicketUpdated extends Notification
{
    use Queueable;

    /**
     * @param  'opened'|'replied'|'status'  $event
     */
    public function __construct(public SupportTicket $ticket, public string $event, public string $actorName, public bool $forClient) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->event) {
            'opened' => __('New support ticket :reference', ['reference' => $this->ticket->reference]),
            'replied' => __('New reply on ticket :reference', ['reference' => $this->ticket->reference]),
            default => __('Ticket :reference is now :status', ['reference' => $this->ticket->reference, 'status' => $this->ticket->status->clientLabel()]),
        };

        return (new MailMessage)
            ->subject($subject)
            ->line($this->ticket->subject)
            ->line(__('By :name.', ['name' => $this->actorName]))
            ->action(__('Open ticket'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'ticket.'.$this->event,
            'ticket' => $this->ticket->ulid,
            'reference' => $this->ticket->reference,
            'status' => $this->ticket->status->value,
            'name' => $this->actorName,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->forClient ? route('portal.support.show', $this->ticket) : route('admin.tickets.show', $this->ticket);
    }
}
