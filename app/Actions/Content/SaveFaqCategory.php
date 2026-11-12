<?php

namespace App\Actions\Content;

use App\Models\FaqCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveFaqCategory
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?FaqCategory $category, array $data, User $editor): FaqCategory
    {
        return DB::transaction(function () use ($category, $data, $editor): FaqCategory {
            $before = $category?->translationSnapshot(FaqCategory::TRANSLATED_FIELDS);

            $category = $category === null
                ? FaqCategory::create(['sort_order' => (int) $data['sort_order']])
                : tap($category)->update(['sort_order' => (int) $data['sort_order']]);

            $category->syncTranslations($data['translations'], FaqCategory::TRANSLATED_FIELDS, 'name');

            $this->audit->record(
                operation: $before === null ? 'faq_category.created' : 'faq_category.updated',
                auditable: $category,
                user: $editor,
                before: $before === null ? null : ['translations' => $before],
                after: ['sort_order' => $category->sort_order, 'translations' => $category->translationSnapshot(FaqCategory::TRANSLATED_FIELDS)],
            );

            return $category;
        });
    }
}
