<?php

namespace App\Actions\Content;

use App\Models\Technology;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveTechnology
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{technology_category_id: int, key: string, icon?: string|null, website_url?: string|null, proficiency: string, is_visible?: bool, sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Technology $technology, array $data, User $editor): Technology
    {
        return DB::transaction(function () use ($technology, $data, $editor): Technology {
            $attributes = Arr::only($data, ['technology_category_id', 'key', 'icon', 'website_url', 'proficiency', 'is_visible', 'sort_order']);
            $before = $technology ? $this->snapshot($technology) : null;

            $technology = $technology === null ? Technology::create($attributes) : tap($technology)->update($attributes);
            $technology->syncTranslations($data['translations'], Technology::TRANSLATED_FIELDS, 'name');

            $this->audit->record(
                operation: $before === null ? 'technology.created' : 'technology.updated',
                auditable: $technology,
                user: $editor,
                before: $before,
                after: $this->snapshot($technology),
            );

            return $technology;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Technology $technology): array
    {
        return [
            ...$technology->only(['technology_category_id', 'key', 'icon', 'website_url', 'is_visible', 'sort_order']),
            'proficiency' => $technology->proficiency->value,
            'translations' => $technology->translationSnapshot(['name', 'description']),
        ];
    }
}
