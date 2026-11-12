<?php

namespace App\Actions\Blog;

use App\Models\PostCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SavePostCategory
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?PostCategory $category, array $data, User $editor): PostCategory
    {
        return DB::transaction(function () use ($category, $data, $editor): PostCategory {
            $attributes = ['sort_order' => (int) $data['sort_order']];
            $before = $category ? $this->snapshot($category) : null;

            $category = $category === null ? PostCategory::create($attributes) : tap($category)->update($attributes);
            $category->syncTranslations($data['translations'], PostCategory::TRANSLATED_FIELDS, 'name');

            $this->audit->record(
                operation: $before === null ? 'post_category.created' : 'post_category.updated',
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
    private function snapshot(PostCategory $category): array
    {
        return ['sort_order' => $category->sort_order, 'translations' => $category->translationSnapshot(['name', 'slug'])];
    }
}
