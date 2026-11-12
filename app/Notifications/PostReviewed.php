<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the author a reviewer approved their article or requested changes (with the reviewer's note).
 */
class PostReviewed extends Notification
{
    use Queueable;

    public function __construct(public Post $post, public string $title, public bool $approved, public string $reviewerName, public ?string $note = null) {}

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
                ? __(':name approved “:title”', ['name' => $this->reviewerName, 'title' => $this->title])
                : __(':name requested changes to “:title”', ['name' => $this->reviewerName, 'title' => $this->title]));

        if (filled($this->note)) {
            $mail->line($this->note);
        }

        return $mail->action(__('Open article'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->approved ? 'post.approved' : 'post.changes_requested',
            'post' => $this->post->ulid,
            'title' => $this->title,
            'name' => $this->reviewerName,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('admin.posts.edit', $this->post);
    }
}
