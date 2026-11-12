<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A refund was rejected, failed, needs a manual refund or was completed. Clients hear only about completed refunds.
 */
class RefundStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public Refund $refund, public bool $forClient) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Refund :reference: :status', ['reference' => $this->refund->reference, 'status' => $this->refund->status->label()]))
            ->line(__('Refund :reference of :amount on payment :payment is now: :status.', [
                'reference' => $this->refund->reference,
                'amount' => $this->refund->formattedAmount(),
                'payment' => $this->refund->payment?->reference,
                'status' => $this->refund->status->label(),
            ]));

        if (! $this->forClient && filled($this->refund->decision_note)) {
            $mail->line(__('Note: :note', ['note' => $this->refund->decision_note]));
        }

        return $mail->action($this->forClient ? __('View payments') : __('Open refunds'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'refund.status',
            'reference' => $this->refund->reference,
            'status' => $this->refund->status->value,
            'amount_minor' => $this->refund->amount_minor,
            'currency' => $this->refund->currency,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->forClient ? route('portal.payments.index') : route('admin.refunds.index');
    }
}
