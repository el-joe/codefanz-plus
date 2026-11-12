<?php

namespace App\Actions\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Converts between repeater rows (one row = every language side by side) and the per-locale JSON lists stored
 * on translation rows (e.g. project key facts, product features).
 *
 * Lists stay index-aligned across languages: a row left blank in one language is stored as null there,
 * so editing never shifts rows between languages. Public pages read only the filled entries.
 */
class TranslatedList
{
    /**
     * @param  list<array{translations?: array<string, array<string, string|null>>}>  $rows
     * @param  list<string>  $fields
     * @param  list<string>  $locales
     * @return array<string, list<array<string, string|null>|null>|null> keyed by locale
     */
    public static function fromRows(array $rows, array $fields, string $requiredField, array $locales): array
    {
        $rows = array_values(array_filter($rows, fn (mixed $row): bool => collect($locales)
            ->contains(fn (string $locale): bool => filled($row['translations'][$locale][$requiredField] ?? null))));

        $lists = [];

        foreach ($locales as $locale) {
            $list = array_map(function (array $row) use ($locale, $fields, $requiredField): ?array {
                $values = $row['translations'][$locale] ?? [];

                if (blank($values[$requiredField] ?? null)) {
                    return null;
                }

                return collect($fields)->mapWithKeys(fn (string $field): array => [$field => filled($values[$field] ?? null) ? trim((string) $values[$field]) : null])->all();
            }, $rows);

            while ($list !== [] && end($list) === null) {
                array_pop($list);
            }

            $lists[$locale] = $list === [] ? null : $list;
        }

        return $lists;
    }

    /**
     * Rebuild repeater rows from the translations of a model.
     *
     * @param  Collection<int, Model>  $translations
     * @param  list<string>  $fields
     * @return list<array{id: null, translations: array<string, array<string, string|null>>}>
     */
    public static function toRows(Collection $translations, string $attribute, array $fields): array
    {
        $count = $translations->map(fn (Model $translation): int => count($translation->{$attribute} ?? []))->max() ?? 0;
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'id' => null,
                'translations' => $translations
                    ->mapWithKeys(fn (Model $translation): array => [
                        $translation->locale => collect($fields)->mapWithKeys(fn (string $field): array => [$field => $translation->{$attribute}[$index][$field] ?? null])->all(),
                    ])
                    ->all(),
            ];
        }

        return $rows;
    }
}
