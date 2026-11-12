<?php

namespace App\Actions\Content;

use App\Models\Statistic;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveStatistic
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{value: numeric-string|int|float, sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Statistic $statistic, array $data, User $editor): Statistic
    {
        return DB::transaction(function () use ($statistic, $data, $editor): Statistic {
            $attributes = ['value' => $data['value'], 'sort_order' => (int) $data['sort_order'], 'updated_by' => $editor->getKey()];
            $before = $statistic ? $this->snapshot($statistic) : null;

            $statistic = $statistic === null
                ? Statistic::create([...$attributes, 'created_by' => $editor->getKey()])
                : tap($statistic)->update($attributes);

            $statistic->syncTranslations($data['translations'], Statistic::TRANSLATED_FIELDS, 'label');

            $this->audit->record(
                operation: $before === null ? 'statistic.created' : 'statistic.updated',
                auditable: $statistic,
                user: $editor,
                before: $before,
                after: $this->snapshot($statistic),
            );

            return $statistic;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Statistic $statistic): array
    {
        return [
            'value' => (string) $statistic->value,
            'sort_order' => $statistic->sort_order,
            'translations' => $statistic->translationSnapshot(Statistic::TRANSLATED_FIELDS),
        ];
    }
}
