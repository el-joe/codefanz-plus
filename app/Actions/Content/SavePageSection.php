<?php

namespace App\Actions\Content;

use App\Models\Language;
use App\Models\PageSection;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Saves a homepage section's visibility, options and per-language copy. A language left completely blank
 * has its translation removed, so the section falls back to the built-in default wording.
 */
class SavePageSection
{
    public const MAX_POINTS = 6;

    public const MAX_ITEMS = 8;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{is_visible?: bool, limit?: int|null, cta_url?: string|null, translations: array<string, array<string, string|null>>, items?: list<array{translations?: array<string, array<string, string|null>>}>}  $data
     */
    public function handle(PageSection $section, array $data, User $editor): PageSection
    {
        return DB::transaction(function () use ($section, $data, $editor): PageSection {
            $before = $this->snapshot($section);

            $settings = array_filter([
                'limit' => $section->type->limit() !== null && filled($data['limit'] ?? null) ? (int) $data['limit'] : null,
                'cta_url' => $section->type->hasCustomCtaUrl() && filled($data['cta_url'] ?? null) ? $data['cta_url'] : null,
            ], fn (mixed $value): bool => $value !== null);

            $section->forceFill([
                'is_visible' => (bool) ($data['is_visible'] ?? false),
                'settings' => $settings === [] ? null : $settings,
                'updated_by' => $editor->getKey(),
            ])->save();

            $locales = Language::active()->keys()->all();
            $items = $section->type->hasItems()
                ? TranslatedList::fromRows($data['items'] ?? [], PageSection::ITEM_FIELDS, 'title', $locales)
                : [];

            foreach ($locales as $locale) {
                $fields = $data['translations'][$locale] ?? [];
                $values = [
                    'eyebrow' => self::clean($fields['eyebrow'] ?? null),
                    'heading' => self::clean($fields['heading'] ?? null),
                    'subheading' => self::clean($fields['subheading'] ?? null),
                    'cta_label' => self::clean($fields['cta_label'] ?? null),
                    'points' => $section->type->hasPoints() ? self::points($fields['points'] ?? null) : null,
                    'items' => $items[$locale] ?? null,
                ];

                if (array_filter($values, fn (mixed $value): bool => $value !== null) === []) {
                    $section->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $section->translations()->updateOrCreate(['locale' => $locale], $values);
            }

            $section->unsetRelation('translations');

            $this->audit->record(operation: 'page_section.updated', auditable: $section, user: $editor, before: $before, after: $this->snapshot($section));

            return $section;
        });
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>|null
     */
    private static function points(?string $text): ?array
    {
        $points = collect(preg_split('/\R/u', (string) $text))
            ->map(fn (string $line): string => mb_substr(trim($line), 0, 100))
            ->filter()
            ->take(self::MAX_POINTS)
            ->values()
            ->all();

        return $points === [] ? null : $points;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PageSection $section): array
    {
        return [
            'type' => $section->type->value,
            'is_visible' => $section->is_visible,
            'sort_order' => $section->sort_order,
            'settings' => $section->settings,
            'translations' => $section->translationSnapshot(['eyebrow', 'heading', 'subheading', 'cta_label']),
        ];
    }
}
