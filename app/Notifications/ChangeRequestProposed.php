<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells reviewers holding the required permission that an AI / MCP proposal is waiting in the approval queue.
 */
class ChangeRequestProposed extends Notification
{
    use Queueable;

    public function __construct(public ChangeRequest $changeRequest) {}

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
            ->subject(__('Approval needed: :reference', ['reference' => $this->changeRequest->reference]))
            ->line(__(':actor proposed a change: :summary', ['actor' => $this->changeRequest->actor_label ?? __('System'), 'summary' => $this->changeRequest->summary]));

        if ($this->changeRequest->hasGuardrailFlags()) {
            $mail->line(__('Automatic checks flagged this proposal. Review the flags before approving.'));
        }

        return $mail->action(__('Review proposal'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'change_request.proposed',
            'reference' => $this->changeRequest->reference,
            'origin' => $this->changeRequest->origin->value,
            'summary' => $this->changeRequest->summary,
            'flagged' => $this->changeRequest->hasGuardrailFlags(),
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('admin.approvals.show', $this->changeRequest);
    }
}
