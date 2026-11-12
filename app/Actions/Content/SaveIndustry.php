<?php

namespace App\Actions\Content;

use App\Models\Industry;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveIndustry
{
    public const MAX_SOLUTIONS = 12;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{icon?: string|null, sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Industry $industry, array $data, User $editor): Industry
    {
        return DB::transaction(function () use ($industry, $data, $editor): Industry {
            $attributes = ['icon' => $data['icon'] ?? null, 'sort_order' => (int) $data['sort_order'], 'updated_by' => $editor->getKey()];
            $before = $industry ? $this->snapshot($industry) : null;

            $industry = $industry === null
                ? Industry::create([...$attributes, 'created_by' => $editor->getKey()])
                : tap($industry)->update($attributes);

            $translations = collect($data['translations'])
                ->map(fn (array $fields): array => [...$fields, 'typical_solutions' => $this->solutions($fields['typical_solutions'] ?? null)])
                ->all();

            $industry->syncTranslations($translations, Industry::TRANSLATED_FIELDS, 'name');

            $this->audit->record(
                operation: $before === null ? 'industry.created' : 'industry.updated',
                auditable: $industry,
                user: $editor,
                before: $before,
                after: $this->snapshot($industry),
            );

            return $industry;
        });
    }

    /**
     * One solution per line, trimmed, de-duplicated and capped.
     *
     * @return list<string>|null
     */
    private function solutions(?string $lines): ?array
    {
        $items = collect(preg_split('/\R/u', (string) $lines))
            ->map(fn (string $line): string => mb_substr(trim($line), 0, 60))
            ->filter()
            ->unique()
            ->take(self::MAX_SOLUTIONS)
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Industry $industry): array
    {
        return [
            'icon' => $industry->icon,
            'sort_order' => $industry->sort_order,
            'translations' => $industry->translationSnapshot(['name', 'slug', 'summary', 'typical_solutions']),
        ];
    }
}
