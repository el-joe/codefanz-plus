<?php

namespace App\Enums;

enum RadarProjectStatus: string
{
    case New = 'new';
    case Shortlisted = 'shortlisted';
    case ProposalDrafted = 'proposal_drafted';
    case Applied = 'applied';
    case Won = 'won';
    case Lost = 'lost';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Shortlisted => __('Shortlisted'),
            self::ProposalDrafted => __('Proposal drafted'),
            self::Applied => __('Applied on platform'),
            self::Won => __('Won'),
            self::Lost => __('Lost'),
            self::Ignored => __('Ignored'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'neutral',
            self::Shortlisted => 'brand',
            self::ProposalDrafted => 'info',
            self::Applied => 'warning',
            self::Won => 'success',
            self::Lost => 'danger',
            self::Ignored => 'neutral',
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
