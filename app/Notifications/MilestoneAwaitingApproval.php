<?php

namespace App\Notifications;

use App\Models\Milestone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks the customer's portal users to review a milestone, in their own language.
 */
class MilestoneAwaitingApproval extends Notification
{
    use Queueable;

    public function __construct(public Milestone $milestone) {}

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
            ->subject(__('Milestone ready for your review: :title', ['title' => $this->milestone->title]))
            ->line(__('The CodeFanz team finished “:title” for :project and needs your approval.', ['title' => $this->milestone->title, 'project' => $this->milestone->project->name]))
            ->action(__('Review milestone'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'milestone.awaiting_approval',
            'project' => $this->milestone->project->ulid,
            'project_name' => $this->milestone->project->name,
            'title' => $this->milestone->title,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('portal.projects.show', ['project' => $this->milestone->project, 'tab' => 'milestones']);
    }
}
