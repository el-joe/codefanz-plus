<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the team a contact-form message arrived. Sent to the contact address from Settings
 * (the "log" mailer in development writes it to the log).
 */
class ContactMessageReceived extends Notification
{
    use Queueable;

    public function __construct(public ContactMessage $contactMessage) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->contactMessage;

        return (new MailMessage)
            ->subject(__('New contact message: :topic', ['topic' => $message->topic->label()]))
            ->replyTo($message->email, $message->name)
            ->line(__('From: :name <:email>', ['name' => $message->name, 'email' => $message->email]))
            ->when($message->phone, fn (MailMessage $mail) => $mail->line(__('Phone: :phone', ['phone' => $message->phone])))
            ->line(__('Preferred language: :language', ['language' => strtoupper($message->preferred_locale)]))
            ->line($message->message)
            ->action(__('Open the inbox'), route('admin.contact-messages.show', $message));
    }
}
