<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a new staff member to choose a password. The link uses the password broker, so it expires with it.
 */
class StaffInvitation extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

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
            ->subject(__('You have been invited to :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('An account has been created for you on the :app admin. Choose a password to activate it.', ['app' => config('app.name')]))
            ->action(__('Choose your password'), $url)
            ->line(__('This link expires in :count minutes. Ask an administrator to resend it if it expires.', ['count' => $minutes]))
            ->line(__('Your role may require two-factor authentication, which you will set up after signing in.'));
    }
}
