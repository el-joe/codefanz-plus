<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Post;
use App\Models\User;

/**
 * Authors (content.manage) write and submit; reviewers (content.approve) approve, request changes, publish,
 * schedule and archive. Nobody reviews an AI-origin draft they are the author of (four-eyes for AI content).
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function submit(User $user, Post $post): bool
    {
        return $user->can(Permission::ContentManage->value);
    }

    public function review(User $user, Post $post): bool
    {
        if (! $user->can(Permission::ContentApprove->value)) {
            return false;
        }

        return ! ($post->isAiOrigin() && $post->author_id !== null && $post->author_id === $user->getKey());
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can(Permission::ContentManage->value);
    }
}
