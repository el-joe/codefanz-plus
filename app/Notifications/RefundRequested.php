<?php

namespace App\Notifications;

use App\Models\Refund;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A refund awaits finance approval (sent to refund approvers other than the requester).
 */
class RefundRequested extends Notification
{
    use Queueable;

    public function __construct(public Refund $refund, public string $requesterName) {}

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
            ->subject(__('Refund awaiting approval: :amount', ['amount' => $this->refund->formattedAmount()]))
            ->line(__(':name requested refund :reference of :amount on payment :payment.', [
                'name' => $this->requesterName,
                'reference' => $this->refund->reference,
                'amount' => $this->refund->formattedAmount(),
                'payment' => $this->refund->payment?->reference,
            ]))
            ->line(__('Reason: :reason', ['reason' => $this->refund->reason]))
            ->action(__('Review refund'), route('admin.refunds.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'refund.requested',
            'reference' => $this->refund->reference,
            'name' => $this->requesterName,
            'amount_minor' => $this->refund->amount_minor,
            'currency' => $this->refund->currency,
            'url' => route('admin.refunds.index'),
        ];
    }
}
