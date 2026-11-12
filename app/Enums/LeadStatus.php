<?php

namespace App\Enums;

/**
 * Sales pipeline. Open statuses may move freely between each other; Won is final; Lost may be reopened.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Contacted => __('Contacted'),
            self::Qualified => __('Qualified'),
            self::Proposal => __('Proposal'),
            self::Negotiation => __('Negotiation'),
            self::Won => __('Won'),
            self::Lost => __('Lost'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'neutral',
            self::Contacted => 'info',
            self::Qualified => 'brand',
            self::Proposal => 'warning',
            self::Negotiation => 'accent',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Won => [],
            self::Lost => [self::New, self::Contacted, self::Qualified],
            default => array_values(array_filter(self::cases(), fn (self $status): bool => $status !== $this)),
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isOpen()));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
