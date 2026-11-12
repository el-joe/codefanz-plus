<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a team member a lead was assigned to them, in their own language.
 */
class LeadAssigned extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead, public ?User $assignedBy = null) {}

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
            ->subject(__('Lead :reference was assigned to you', ['reference' => $this->lead->reference]))
            ->line(__(':name’s request is now yours to follow up.', ['name' => $this->lead->name]))
            ->when($this->assignedBy, fn (MailMessage $mail) => $mail->line(__('Assigned by :user.', ['user' => $this->assignedBy->name])))
            ->action(__('Open lead'), route('admin.leads.show', $this->lead));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'lead.assigned',
            'lead' => $this->lead->ulid,
            'reference' => $this->lead->reference,
            'name' => $this->lead->name,
            'by' => $this->assignedBy?->name,
        ];
    }
}
