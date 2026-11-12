<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SocialPost;
use App\Models\User;

/**
 * Writers (social.manage) compose, edit, submit, cancel and record manual exports; reviewers (social.approve)
 * approve, request changes, schedule, reschedule, publish and retry. Nobody reviews an AI post they generated.
 */
class SocialPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SocialManage->value);
    }

    public function view(User $user, SocialPost $post): bool
    {
        return $user->can(Permission::SocialManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SocialManage->value);
    }

    public function update(User $user, SocialPost $post): bool
    {
        return $user->can(Permission::SocialManage->value);
    }

    public function review(User $user, SocialPost $post): bool
    {
        return $user->can(Permission::SocialApprove->value) && ! $post->isGeneratedBy($user);
    }
}
