<?php

namespace App\Models;

use App\Enums\PageSectionType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A homepage section (fixed type). Copy is translated; blank copy falls back to the built-in default wording.
 *
 * @method PageSectionTranslation|null translation(?string $locale = null)
 * @method PageSectionTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['page', 'type', 'sort_order', 'is_visible', 'settings', 'updated_by'])]
class PageSection extends Model
{
    use HasTranslations;

    public const HOME = 'home';

    public const TRANSLATED_FIELDS = ['eyebrow', 'heading', 'subheading', 'cta_label', 'points', 'items'];

    public const ITEM_FIELDS = ['title', 'text'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'page' => self::HOME,
        'is_visible' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PageSectionType::class,
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * Create any missing homepage section rows (structural data: type + default order, no copy).
     */
    public static function ensureHomeSections(): void
    {
        $existing = static::query()->home()->pluck('type')->map(fn (PageSectionType $type): string => $type->value)->all();

        foreach (PageSectionType::cases() as $position => $type) {
            if (! in_array($type->value, $existing, true)) {
                static::query()->create([
                    'page' => self::HOME,
                    'type' => $type,
                    'sort_order' => $position,
                    'is_visible' => $type->isAvailable(),
                ]);
            }
        }
    }

    /**
     * Unsaved section rows in default order, used before the sections are seeded.
     *
     * @return Collection<int, PageSection>
     */
    public static function defaultHomeSections(): Collection
    {
        return collect(PageSectionType::cases())
            ->map(fn (PageSectionType $type, int $position): PageSection => (new static)->forceFill([
                'page' => self::HOME, 'type' => $type, 'sort_order' => $position, 'is_visible' => $type->isAvailable(),
            ])->setRelation('translations', new EloquentCollection))
            ->values();
    }

    /**
     * @param  Builder<PageSection>  $query
     */
    public function scopeHome(Builder $query): void
    {
        $query->where('page', self::HOME);
    }

    /**
     * @param  Builder<PageSection>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Number of catalog items to show, clamped to the type's maximum.
     */
    public function limit(): int
    {
        $limit = $this->type->limit();

        if ($limit === null) {
            return 0;
        }

        return max(1, min($limit['max'], (int) ($this->settings['limit'] ?? $limit['default'])));
    }

    public function ctaUrl(): ?string
    {
        $url = $this->settings['cta_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Copy for the current locale: the translation when filled, otherwise the default wording.
     *
     * @return array{eyebrow: string|null, heading: string|null, subheading: string|null, cta_label: string|null, points: list<string>, items: list<array{title: string, text: string|null}>, is_default_heading: bool}
     */
    public function copy(?string $locale = null): array
    {
        $translation = $this->relationLoaded('translations') ? $this->exactTranslation($locale ?? app()->getLocale()) : null;
        $defaults = $this->type->defaults($locale);
        $copy = [];

        foreach (['eyebrow', 'heading', 'subheading', 'cta_label'] as $field) {
            $copy[$field] = filled($translation?->{$field}) ? $translation->{$field} : $defaults[$field];
        }

        return [
            ...$copy,
            'points' => array_values(array_filter($translation?->points ?? [], fn (mixed $point): bool => is_string($point) && $point !== '')),
            'items' => array_values(array_filter($translation?->items ?? [], fn (mixed $item): bool => is_array($item) && filled($item['title'] ?? null))),
            'is_default_heading' => blank($translation?->heading),
        ];
    }
}
