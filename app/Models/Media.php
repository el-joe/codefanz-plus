<?php

namespace App\Models;

use App\Enums\MediaStatus;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['uuid', 'original_disk', 'original_path', 'original_name', 'mime_type', 'size', 'checksum', 'width', 'height', 'is_animated', 'alt', 'folder', 'status', 'error', 'uploaded_by'])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'alt' => 'array',
            'is_animated' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<MediaVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class)->orderBy('width');
    }

    /**
     * @return HasMany<Mediable, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(Mediable::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<Media>  $query
     */
    public function scopeUnused(Builder $query): void
    {
        $query->whereDoesntHave('usages');
    }

    public function altText(?string $locale = null): string
    {
        $alt = $this->alt ?? [];

        return $alt[$locale ?? app()->getLocale()] ?? $alt['en'] ?? '';
    }

    /**
     * Variants of a format ordered by width.
     *
     * @return Collection<int, MediaVariant>
     */
    public function variantsFor(string $format): Collection
    {
        return $this->variants->where('format', $format)->values();
    }

    public function largestVariant(string $format = 'webp'): ?MediaVariant
    {
        return $this->variantsFor($format)->last();
    }

    public function deliveredBytes(): ?int
    {
        return $this->largestVariant('avif')?->size;
    }
}
