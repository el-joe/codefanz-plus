<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells editorial reviewers (content.approve) that an article is waiting for review.
 */
class PostSubmittedForReview extends Notification
{
    use Queueable;

    public function __construct(public Post $post, public string $title, public string $submitterName) {}

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
            ->subject(__('Article ready for review: “:title”', ['title' => $this->title]))
            ->line(__(':name submitted an article for review.', ['name' => $this->submitterName]));

        if ($this->post->isAiOrigin()) {
            $mail->line(__('This is an AI-generated draft. Check every fact before approving.'));
        }

        return $mail->action(__('Review article'), $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'post.submitted',
            'post' => $this->post->ulid,
            'title' => $this->title,
            'name' => $this->submitterName,
            'ai_origin' => $this->post->isAiOrigin(),
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('admin.posts.edit', $this->post);
    }
}
