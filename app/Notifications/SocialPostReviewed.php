<?php

namespace App\Notifications;

use App\Models\SocialPost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the author of a social post that it was approved or that changes were requested.
 */
class SocialPostReviewed extends Notification
{
    use Queueable;

    public function __construct(public SocialPost $post, public bool $approved, public string $reviewerName, public ?string $note = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->approved ? 'social_post.approved' : 'social_post.changes_requested',
            'post' => $this->post->ulid,
            'title' => $this->post->title,
            'name' => $this->reviewerName,
            'note' => $this->note,
            'url' => route('admin.social.posts.show', $this->post),
        ];
    }
}
