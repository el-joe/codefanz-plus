<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A verified payment: the customer's portal users get their receipt, finance staff get the transaction.
 */
class PaymentReceived extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment, public bool $forClient) {}

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
            ->subject(__('Payment received: :amount', ['amount' => $this->payment->formattedAmount()]))
            ->line(__('Payment :reference of :amount was verified with :gateway.', [
                'reference' => $this->payment->reference,
                'amount' => $this->payment->formattedAmount(),
                'gateway' => $this->payment->gatewayLabel(),
            ]));

        if ($this->payment->project) {
            $mail->line(__('Project: :project', ['project' => $this->payment->project->name]));
        }

        return $mail->action($this->forClient ? __('View receipt') : __('Open payment'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'payment.received',
            'reference' => $this->payment->reference,
            'amount_minor' => $this->payment->amount_minor,
            'currency' => $this->payment->currency,
            'project_name' => $this->payment->project?->name,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->forClient
            ? route('portal.payments.receipt', $this->payment)
            : route('admin.payments.show', $this->payment);
    }
}
