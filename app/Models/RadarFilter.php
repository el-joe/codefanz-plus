<?php

namespace App\Models;

use Database\Factories\RadarFilterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved filter profile ("Laravel & SaaS", "Flutter", …): a view of the radar and, when alerts are on, the gate
 * for Telegram alerts. Filters never delete or hide projects from the full list.
 *
 * rules: {include_keywords: list, exclude_keywords: list, skills: list, min_budget: int|null (major units, USD),
 * platforms: list<key>, languages: list, countries: list, min_score: int|null, budget_type: string|null}
 */
#[Fillable(['name', 'rules', 'is_active', 'alerts_enabled', 'sort_order', 'created_by', 'updated_by'])]
class RadarFilter extends Model
{
    /** @use HasFactory<RadarFilterFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'alerts_enabled' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
            'alerts_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public function ruleList(string $key): array
    {
        return array_values(array_filter(array_map(fn ($value): string => trim((string) $value), (array) ($this->rules[$key] ?? [])), 'filled'));
    }

    public function ruleNumber(string $key): ?int
    {
        $value = $this->rules[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
