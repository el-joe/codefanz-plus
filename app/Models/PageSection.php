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
 * A managed page section (fixed type) on the homepage, About or Careers page. Copy is translated; blank copy
 * falls back to the built-in default wording.
 *
 * @method PageSectionTranslation|null translation(?string $locale = null)
 * @method PageSectionTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['page', 'type', 'sort_order', 'is_visible', 'settings', 'updated_by'])]
class PageSection extends Model
{
    use HasTranslations;

    public const HOME = 'home';

    public const ABOUT = 'about';

    public const CAREERS = 'careers';

    /**
     * Section-managed pages and their public route names.
     */
    public const PAGES = [
        self::HOME => 'home',
        self::ABOUT => 'about',
        self::CAREERS => 'careers.index',
    ];

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
        static::ensureSections(self::HOME);
    }

    /**
     * Create any missing section rows of one page (structural data: type + default order, no copy).
     */
    public static function ensureSections(string $page): void
    {
        $existing = static::query()->where('page', $page)->pluck('type')->map(fn (PageSectionType $type): string => $type->value)->all();

        foreach (PageSectionType::forPage($page) as $position => $type) {
            if (! in_array($type->value, $existing, true)) {
                static::query()->create([
                    'page' => $page,
                    'type' => $type,
                    'sort_order' => $position,
                    'is_visible' => $type->isAvailable(),
                ]);
            }
        }
    }

    /**
     * Unsaved homepage section rows in default order, used before the sections are seeded.
     *
     * @return Collection<int, PageSection>
     */
    public static function defaultHomeSections(): Collection
    {
        return static::defaultSections(self::HOME);
    }

    /**
     * Unsaved section rows of one page in default order, used before the sections are seeded.
     *
     * @return Collection<int, PageSection>
     */
    public static function defaultSections(string $page): Collection
    {
        return collect(PageSectionType::forPage($page))
            ->map(fn (PageSectionType $type, int $position): PageSection => (new static)->forceFill([
                'page' => $page, 'type' => $type, 'sort_order' => $position, 'is_visible' => $type->isAvailable(),
            ])->setRelation('translations', new EloquentCollection))
            ->values();
    }

    /**
     * Visible sections of a page keyed by type value, with translations (default rows before seeding).
     *
     * @return Collection<string, PageSection>
     */
    public static function visibleFor(string $page): Collection
    {
        $sections = static::query()->where('page', $page)->with('translations')->ordered()->get();

        if ($sections->isEmpty()) {
            $sections = static::defaultSections($page);
        }

        return $sections
            ->filter(fn (PageSection $section): bool => $section->is_visible && $section->type->isAvailable())
            ->keyBy(fn (PageSection $section): string => $section->type->value);
    }

    public static function pageLabel(string $page): string
    {
        return match ($page) {
            self::ABOUT => __('About page'),
            self::CAREERS => __('Careers page'),
            default => __('Homepage'),
        };
    }

    /**
     * Public URL of the page this section belongs to.
     */
    public function pageUrl(?string $locale = null): string
    {
        return route(self::PAGES[$this->page] ?? 'home', ['locale' => $locale ?? app()->getLocale()]);
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
