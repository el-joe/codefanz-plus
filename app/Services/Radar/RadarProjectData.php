<?php

namespace App\Services\Radar;

use App\Enums\RadarBudgetType;
use Carbon\CarbonImmutable;

/**
 * A project normalized from any connector or alert email. Money is in minor units of $currency.
 */
final readonly class RadarProjectData
{
    /**
     * @param  list<string>  $skills
     * @param  array<string, mixed>  $source  raw platform data (kept for audit / re-parsing)
     */
    public function __construct(
        public string $externalId,
        public string $url,
        public string $title,
        public ?string $description = null,
        public ?int $budgetMinMinor = null,
        public ?int $budgetMaxMinor = null,
        public ?string $currency = null,
        public RadarBudgetType $budgetType = RadarBudgetType::Unknown,
        public array $skills = [],
        public ?string $clientCountry = null,
        public ?float $clientRating = null,
        public ?int $clientSpentMinor = null,
        public ?bool $clientPaymentVerified = null,
        public ?int $proposalsCount = null,
        public ?CarbonImmutable $postedAt = null,
        public array $source = [],
    ) {}

    /**
     * Changes to these fields re-score the project and may alert again.
     */
    public function contentHash(): string
    {
        return sha1((string) json_encode([
            trim($this->title),
            trim((string) $this->description),
            $this->budgetMinMinor,
            $this->budgetMaxMinor,
            $this->currency,
            $this->budgetType->value,
            $this->skills,
        ], JSON_UNESCAPED_UNICODE));
    }
}
