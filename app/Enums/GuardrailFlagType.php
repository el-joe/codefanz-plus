<?php

namespace App\Enums;

/**
 * Post-generation checks on AI output. Flags never block a proposal; they are shown to the author and reviewers and
 * stop AI items from being bulk-approved.
 */
enum GuardrailFlagType: string
{
    case BannedClaim = 'banned_claim';
    case UnverifiedNumber = 'unverified_number';
    case Price = 'price';
    case UnknownLink = 'unknown_link';

    public function label(): string
    {
        return match ($this) {
            self::BannedClaim => __('Banned claim'),
            self::UnverifiedNumber => __('Number not found in your inputs'),
            self::Price => __('Price or amount'),
            self::UnknownLink => __('Link to an unknown domain'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::BannedClaim => __('Brand rules forbid this wording (guarantees, superlatives, invented proof).'),
            self::UnverifiedNumber => __('Statistics and figures must come from a real source. Verify or remove it.'),
            self::Price => __('Prices are quoted per project. Remove amounts unless they are approved.'),
            self::UnknownLink => __('Only links to this website or approved domains are allowed.'),
        };
    }
}
