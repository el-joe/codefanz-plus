<?php

namespace App\Models;

use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The brand voice every Content Studio prompt carries: bilingual tone and audience, do / don't rules, banned claims
 * (checked again after generation) and the EN ↔ AR terminology glossary.
 */
#[Fillable(['name', 'is_default', 'tone', 'audience', 'do_rules', 'dont_rules', 'banned_claims', 'glossary', 'allowed_domains', 'updated_by'])]
class BrandProfile extends Model
{
    /** @use HasFactory<BrandProfileFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'tone' => 'array',
            'audience' => 'array',
            'do_rules' => 'array',
            'dont_rules' => 'array',
            'banned_claims' => 'array',
            'glossary' => 'array',
            'allowed_domains' => 'array',
            'lock_version' => 'integer',
        ];
    }

    /**
     * The profile used by the studio (created on demand so the studio always has one).
     */
    public static function current(): self
    {
        return self::query()->where('is_default', true)->first()
            ?? self::query()->firstOrCreate(['is_default' => true], ['name' => 'CodeFanz']);
    }

    /**
     * @return list<string>
     */
    public function rulesFor(string $column, string $locale): array
    {
        $rules = $this->{$column}[$locale] ?? $this->{$column}['en'] ?? [];

        return array_values(array_filter(array_map('strval', (array) $rules), 'filled'));
    }

    public function textFor(string $column, string $locale): string
    {
        return (string) ($this->{$column}[$locale] ?? $this->{$column}['en'] ?? '');
    }

    /**
     * @return list<array{en: string, ar: string}>
     */
    public function glossaryPairs(): array
    {
        return array_values(array_filter(array_map(
            fn ($pair): array => ['en' => trim((string) ($pair['en'] ?? '')), 'ar' => trim((string) ($pair['ar'] ?? ''))],
            (array) $this->glossary,
        ), fn (array $pair): bool => $pair['en'] !== '' && $pair['ar'] !== ''));
    }

    /**
     * @return list<string>
     */
    public function bannedClaims(): array
    {
        return array_values(array_filter(array_map(fn ($claim): string => trim((string) $claim), (array) $this->banned_claims), 'filled'));
    }

    /**
     * @return list<string>
     */
    public function allowedDomains(): array
    {
        return array_values(array_filter(array_map(fn ($host): string => mb_strtolower(trim((string) $host)), (array) $this->allowed_domains), 'filled'));
    }
}
