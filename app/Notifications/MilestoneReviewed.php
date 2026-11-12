<?php

namespace App\Notifications;

use App\Models\Milestone;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the project team the client approved a milestone or asked for changes.
 */
class MilestoneReviewed extends Notification
{
    use Queueable;

    public function __construct(public Milestone $milestone, public bool $approved, public string $clientName) {}

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
            ->subject($this->approved
                ? __(':name approved “:title”', ['name' => $this->clientName, 'title' => $this->milestone->title])
                : __(':name requested changes to “:title”', ['name' => $this->clientName, 'title' => $this->milestone->title]))
            ->line(__('Project: :project', ['project' => $this->milestone->project->name]));

        if (! $this->approved && filled($this->milestone->client_feedback)) {
            $mail->line($this->milestone->client_feedback);
        }

        return $mail->action(__('Open project'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->approved ? 'milestone.approved' : 'milestone.changes_requested',
            'project' => $this->milestone->project->ulid,
            'project_name' => $this->milestone->project->name,
            'title' => $this->milestone->title,
            'name' => $this->clientName,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('admin.projects.show', ['project' => $this->milestone->project, 'tab' => 'milestones']);
    }
}
