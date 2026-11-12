<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasTranslations;
use Database\Factories\StatisticFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A company figure (e.g. 120 + "Projects delivered"). Numbers are only ever entered by admins.
 *
 * @method StatisticTranslation|null translation(?string $locale = null)
 * @method StatisticTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['value', 'status', 'sort_order', 'created_by', 'updated_by'])]
class Statistic extends Model
{
    /** @use HasFactory<StatisticFactory> */
    use HasFactory, HasTranslations;

    public const TRANSLATED_FIELDS = ['label', 'suffix'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<Statistic>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * @param  Builder<Statistic>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The value with thousands separators and without trailing decimal zeros, e.g. "1,200" or "4.5".
     */
    public function formattedValue(): string
    {
        $value = (float) $this->value;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : (fmod($value * 10, 1.0) === 0.0 ? 1 : 2);

        return number_format($value, $decimals);
    }
}
