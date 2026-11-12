<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Fillable(['code', 'name', 'native_name', 'direction', 'is_default', 'is_active', 'sort_order'])]
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    public const CACHE_KEY = 'languages.active';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active languages keyed by code, falling back to config when the table is empty or unavailable.
     *
     * @return Collection<string, array{code: string, name: string, native_name: string, direction: string, is_default: bool}>
     */
    public static function active(): Collection
    {
        // Cache plain arrays only: the cache store does not unserialize objects.
        $rows = Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['code', 'name', 'native_name', 'direction', 'is_default'])
            ->map(fn (self $language): array => $language->only(['code', 'name', 'native_name', 'direction', 'is_default']))
            ->keyBy('code')
            ->all());

        return $rows !== [] ? collect($rows) : collect(config('codefanz.languages'))->keyBy('code');
    }

    public static function defaultCode(): string
    {
        return static::active()->firstWhere('is_default', true)['code'] ?? config('app.locale');
    }
}
