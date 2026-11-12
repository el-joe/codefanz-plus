<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the person who requested an AI proposal what the reviewer decided.
 */
class ChangeRequestDecided extends Notification
{
    use Queueable;

    public function __construct(public ChangeRequest $changeRequest, public string $reviewerName) {}

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
            ->subject(__(':reference: :status', ['reference' => $this->changeRequest->reference, 'status' => $this->changeRequest->status->label()]))
            ->line(__(':name reviewed your proposal “:summary”: :status.', ['name' => $this->reviewerName, 'summary' => $this->changeRequest->summary, 'status' => $this->changeRequest->status->label()]));

        if (filled($this->changeRequest->review_note)) {
            $mail->line(__('Review note: :note', ['note' => $this->changeRequest->review_note]));
        }

        return $mail->action(__('Open the request'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'change_request.decided',
            'reference' => $this->changeRequest->reference,
            'status' => $this->changeRequest->status->value,
            'name' => $this->reviewerName,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('admin.approvals.show', $this->changeRequest);
    }
}
