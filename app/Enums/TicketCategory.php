<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Bug = 'bug';
    case Question = 'question';
    case ChangeRequest = 'change_request';
    case Access = 'access';
    case Billing = 'billing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bug => __('Something is not working'),
            self::Question => __('Question'),
            self::ChangeRequest => __('Change request'),
            self::Access => __('Access or accounts'),
            self::Billing => __('Billing'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $category): array => [$category->value => $category->label()])->all();
    }
}
