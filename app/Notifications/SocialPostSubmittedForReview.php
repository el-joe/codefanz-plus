<?php

namespace App\Notifications;

use App\Models\SocialPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells social reviewers (social.approve) that a post is waiting for approval.
 */
class SocialPostSubmittedForReview extends Notification
{
    use Queueable;

    public function __construct(public SocialPost $post, public string $submitterName) {}

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
            ->subject(__('Social post ready for approval: “:title”', ['title' => $this->post->title]))
            ->line(__(':name submitted a social post for approval.', ['name' => $this->submitterName]));

        if ($this->post->isAiOrigin()) {
            $mail->line(__('This post was written with AI. Check every fact before approving.'));
        }

        return $mail->action(__('Review post'), route('admin.social.posts.show', $this->post));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'social_post.submitted',
            'post' => $this->post->ulid,
            'title' => $this->post->title,
            'name' => $this->submitterName,
            'ai_origin' => $this->post->isAiOrigin(),
            'url' => route('admin.social.posts.show', $this->post),
        ];
    }
}
