<?php

namespace App\Actions\Content;

use App\Models\Career;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveCareer
{
    public const MAX_LIST_ITEMS = 15;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{employment_type: string, is_remote?: bool, closes_at?: string|null, sort_order: int, translations: array<string, array<string, string|null>>}  $data
     */
    public function handle(?Career $career, array $data, User $editor): Career
    {
        return DB::transaction(function () use ($career, $data, $editor): Career {
            $attributes = [
                'employment_type' => $data['employment_type'],
                'is_remote' => (bool) ($data['is_remote'] ?? false),
                'closes_at' => $data['closes_at'] ?? null,
                'sort_order' => (int) $data['sort_order'],
                'updated_by' => $editor->getKey(),
            ];
            $before = $career ? $this->snapshot($career) : null;

            $career = $career === null
                ? Career::create([...$attributes, 'created_by' => $editor->getKey()])
                : tap($career)->update($attributes);

            // Any save makes an open SEO-editor form stale (optimistic locking).
            if ($before !== null) {
                $career->forceFill(['lock_version' => $career->lock_version + 1])->save();
            }

            $translations = collect($data['translations'])
                ->map(fn (array $fields): array => [
                    ...$fields,
                    'responsibilities' => self::lines($fields['responsibilities'] ?? null),
                    'requirements' => self::lines($fields['requirements'] ?? null),
                ])
                ->all();

            $career->syncTranslations($translations, Career::TRANSLATED_FIELDS, 'title');

            $this->audit->record(
                operation: $before === null ? 'career.created' : 'career.updated',
                auditable: $career,
                user: $editor,
                before: $before,
                after: $this->snapshot($career),
            );

            return $career;
        });
    }

    /**
     * One entry per line, trimmed and capped.
     *
     * @return list<string>|null
     */
    public static function lines(?string $text): ?array
    {
        $items = collect(preg_split('/\R/u', (string) $text))
            ->map(fn (string $line): string => mb_substr((string) preg_replace('/^[\s\-•*]+|\s+$/u', '', $line), 0, 200))
            ->filter()
            ->take(self::MAX_LIST_ITEMS)
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Career $career): array
    {
        return [
            'employment_type' => $career->employment_type->value,
            'is_remote' => $career->is_remote,
            'closes_at' => $career->closes_at?->toDateString(),
            'sort_order' => $career->sort_order,
            'translations' => $career->translationSnapshot(['title', 'slug', 'department', 'location', 'summary']),
        ];
    }
}
