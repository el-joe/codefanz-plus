<?php

namespace App\Enums;

/**
 * Milestone workflow. Staff start, submit and complete milestones; the client approves or requests changes
 * while a milestone is awaiting approval. A milestone may only start once every dependency is completed.
 */
enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Upcoming'),
            self::InProgress => __('In progress'),
            self::AwaitingApproval => __('Awaiting approval'),
            self::Approved => __('Approved'),
            self::Completed => __('Completed'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::InProgress => 'info',
            self::AwaitingApproval => 'warning',
            self::Approved => 'brand',
            self::Completed => 'success',
        };
    }

    /**
     * Counts towards project progress (signed off by the client or delivered).
     */
    public function isDone(): bool
    {
        return in_array($this, [self::Approved, self::Completed], true);
    }

    /**
     * Transitions staff may make from the admin. Approval itself belongs to the client.
     *
     * @return list<self>
     */
    public function staffTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress],
            self::InProgress => [self::Pending, self::AwaitingApproval, self::Completed],
            self::AwaitingApproval => [self::InProgress],
            self::Approved => [self::Completed, self::InProgress],
            self::Completed => [self::InProgress],
        };
    }

    public function staffCanMoveTo(self $status): bool
    {
        return in_array($status, $this->staffTransitions(), true);
    }

    /**
     * Label of the admin action that moves a milestone into this status.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Pending => __('Move back to upcoming'),
            self::InProgress => __('Start'),
            self::AwaitingApproval => __('Send for client approval'),
            self::Approved => __('Approve'),
            self::Completed => __('Mark completed'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
