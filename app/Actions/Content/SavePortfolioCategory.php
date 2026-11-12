<?php

namespace App\Actions\Content;

use App\Models\PortfolioCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SavePortfolioCategory
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?PortfolioCategory $category, array $data, User $editor): PortfolioCategory
    {
        return DB::transaction(function () use ($category, $data, $editor): PortfolioCategory {
            $attributes = ['sort_order' => (int) $data['sort_order']];
            $before = $category ? $this->snapshot($category) : null;

            $category = $category === null ? PortfolioCategory::create($attributes) : tap($category)->update($attributes);
            $category->syncTranslations($data['translations'], PortfolioCategory::TRANSLATED_FIELDS, 'name');

            $this->audit->record(
                operation: $before === null ? 'portfolio_category.created' : 'portfolio_category.updated',
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
    private function snapshot(PortfolioCategory $category): array
    {
        return ['sort_order' => $category->sort_order, 'translations' => $category->translationSnapshot(['name', 'slug'])];
    }
}
