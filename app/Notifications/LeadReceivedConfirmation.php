<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Auto-reply to the visitor in the language they chose. Only the reference is echoed back —
 * never the project description, phone number or attachments.
 */
class LeadReceivedConfirmation extends Notification
{
    use Queueable;

    public function __construct(public Lead $lead) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('We received your request (:reference)', ['reference' => $this->lead->reference]))
            ->greeting(__('Hello :name,', ['name' => $this->lead->name]))
            ->line(__('Thank you for contacting CodeFanz. Your reference number is :reference.', ['reference' => $this->lead->reference]))
            ->line(__('Our team reviews every request and replies within one business day.'))
            ->line(__('If you did not send this request, you can ignore this email.'));
    }
}
