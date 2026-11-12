<?php

namespace App\Actions\Content;

use App\Models\TechnologyCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveTechnologyCategory
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{key: string, sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?TechnologyCategory $category, array $data, User $editor): TechnologyCategory
    {
        return DB::transaction(function () use ($category, $data, $editor): TechnologyCategory {
            $attributes = ['key' => $data['key'], 'sort_order' => (int) $data['sort_order']];
            $before = $category ? $this->snapshot($category) : null;

            $category = $category === null ? TechnologyCategory::create($attributes) : tap($category)->update($attributes);
            $category->syncTranslations($data['translations'], TechnologyCategory::TRANSLATED_FIELDS, 'name');

            $this->audit->record(
                operation: $before === null ? 'technology_category.created' : 'technology_category.updated',
                auditable: $category,
                user: $editor,
                before: $before,
                after: $this->snapshot($category),
            );

            return $category;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TechnologyCategory $category): array
    {
        return [...$category->only(['key', 'sort_order']), 'translations' => $category->translationSnapshot(['name'])];
    }
}
