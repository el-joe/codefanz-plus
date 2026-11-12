<?php

namespace App\Actions\Content;

use App\Models\Faq;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveFaq
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{faq_category_id?: int|null, is_featured?: bool, sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Faq $faq, array $data, User $editor): Faq
    {
        return DB::transaction(function () use ($faq, $data, $editor): Faq {
            $attributes = [
                'faq_category_id' => $data['faq_category_id'] ?? null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'sort_order' => (int) $data['sort_order'],
                'updated_by' => $editor->getKey(),
            ];
            $before = $faq ? $this->snapshot($faq) : null;

            $faq = $faq === null
                ? Faq::create([...$attributes, 'created_by' => $editor->getKey()])
                : tap($faq)->update($attributes);

            $faq->syncTranslations($data['translations'], Faq::TRANSLATED_FIELDS, 'question');

            $this->audit->record(
                operation: $before === null ? 'faq.created' : 'faq.updated',
                auditable: $faq,
                user: $editor,
                before: $before,
                after: $this->snapshot($faq),
            );

            return $faq;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Faq $faq): array
    {
        return [
            ...$faq->only(['faq_category_id', 'is_featured', 'sort_order']),
            'translations' => $faq->translationSnapshot(Faq::TRANSLATED_FIELDS),
        ];
    }
}
