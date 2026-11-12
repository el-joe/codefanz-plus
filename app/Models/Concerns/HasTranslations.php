<?php

namespace App\Models\Concerns;

use App\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Base table + `{entity}_translations` pattern (one row per locale).
 *
 * The translation model defaults to `{Model}Translation` with a `{snake_model}_id` foreign key.
 * Eager-load `translations` before calling translation() — lazy loading is disabled.
 *
 * @mixin Model
 */
trait HasTranslations
{
    /**
     * @return class-string<Model>
     */
    protected function translationModel(): string
    {
        return static::class.'Translation';
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel());
    }

    /**
     * Translation for the locale (current locale by default), falling back to the default language.
     */
    public function translation(?string $locale = null): ?Model
    {
        return $this->exactTranslation($locale ?? app()->getLocale())
            ?? $this->exactTranslation(Language::defaultCode());
    }

    /**
     * Translation for exactly this locale, without fallback (use in edit forms).
     */
    public function exactTranslation(string $locale): ?Model
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    /**
     * Restrict to records that have a translation in the given locale (current locale by default).
     *
     * @param  Builder<static>  $query
     */
    public function scopeTranslatedIn(Builder $query, ?string $locale = null): void
    {
        $query->whereHas('translations', fn (Builder $translations) => $translations->where('locale', $locale ?? app()->getLocale()));
    }

    /**
     * Persist submitted per-locale fields. A locale whose required field is blank has its translation removed.
     *
     * @param  array<string, array<string, mixed>>  $translations  keyed by locale
     * @param  list<string>  $fields
     */
    public function syncTranslations(array $translations, array $fields, string $requiredField = 'title'): void
    {
        foreach ($translations as $locale => $values) {
            if (blank($values[$requiredField] ?? null)) {
                $this->translations()->where('locale', $locale)->delete();

                continue;
            }

            $attributes = array_replace(array_fill_keys($fields, null), Arr::only($values, $fields));

            $this->translations()->updateOrCreate(['locale' => $locale], $attributes);
        }

        $this->unsetRelation('translations');
    }

    /**
     * Translations keyed by locale for audit snapshots.
     *
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    public function translationSnapshot(array $fields): array
    {
        return $this->translations()->get(['locale', ...$fields])->keyBy('locale')->toArray();
    }
}
