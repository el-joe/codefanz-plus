<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the sales team (leads.manage) and the assignee that a lead arrived. Sent in each
 * recipient's own language (User::preferredLocale). No project details in the mail body.
 */
class NewLeadReceived extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

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
            ->subject(__('New lead :reference', ['reference' => $this->lead->reference]))
            ->line(__(':name sent a request through the :source.', ['name' => $this->lead->name, 'source' => $this->lead->source->label()]))
            ->when($this->lead->possible_duplicate_of_id, fn (MailMessage $mail) => $mail->line(__('This email address already has an open lead.')))
            ->action(__('Open lead'), route('admin.leads.show', $this->lead));
    }

    /**
     * Stored as keys, not sentences, so the bell renders in the reader's current language.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'lead.created',
            'lead' => $this->lead->ulid,
            'reference' => $this->lead->reference,
            'name' => $this->lead->name,
            'source' => $this->lead->source->value,
        ];
    }
}
