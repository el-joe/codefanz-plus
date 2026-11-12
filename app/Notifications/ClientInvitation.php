<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a customer's contact to the client portal. Sent in the user's language (User::preferredLocale);
 * the link uses the password broker, so it expires with it.
 */
class ClientInvitation extends Notification
{
    use Queueable;

    public function __construct(public string $token, public string $customerName) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('Your :app client portal access', ['app' => config('app.name')]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You now have access to the :app client portal for :customer.', ['app' => config('app.name'), 'customer' => $this->customerName]))
            ->line(__('Choose a password to sign in. You will be able to follow your projects, payments, documents and messages in one place.'))
            ->action(__('Choose your password'), $url)
            ->line(__('This link expires in :count minutes. Ask your CodeFanz contact to resend it if it expires.', ['count' => $minutes]));
    }
}
