<?php

namespace App\Enums;

enum AutomationRunStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
            self::Rejected => __('Rejected'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Succeeded => 'success',
            self::Failed, self::Rejected => 'danger',
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
