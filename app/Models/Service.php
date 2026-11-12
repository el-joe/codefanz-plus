<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\ServiceTier;
use App\Models\Concerns\HasMediaCollections;
use App\Models\Concerns\HasTranslations;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method ServiceTranslation|null translation(?string $locale = null)
 * @method ServiceTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['parent_id', 'tier', 'icon', 'status', 'is_featured', 'sort_order', 'published_at', 'created_by', 'updated_by'])]
class Service extends Model implements Approvable
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasMediaCollections, HasTranslations, SoftDeletes;

    public const TRANSLATED_FIELDS = ['title', 'slug', 'summary', 'body', 'seo_title', 'seo_description'];

    /**
     * Mirror database defaults so a freshly created instance reports the correct version.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lock_version' => 1,
        'tier' => 'core',
        'status' => 'draft',
        'sort_order' => 0,
        'is_featured' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => ServiceTier::class,
            'status' => ContentStatus::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'lock_version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'parent_id');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Service::class, 'parent_id');
    }

    /**
     * @return HasMany<ServiceFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(ServiceFeature::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ServiceFaq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(ServiceFaq::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Technologies used to deliver this service (the "tech stack"). This does not mark a technology as verified.
     *
     * @return BelongsToMany<Technology, $this>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class)->withTimestamps();
    }

    /**
     * @return MorphToMany<Media, $this>
     */
    public function cover(): MorphToMany
    {
        return $this->mediaIn('cover');
    }

    public function coverImage(): ?Media
    {
        return $this->cover->first();
    }

    public function approvableVersion(): int
    {
        return (int) $this->lock_version;
    }

    public function approvableAttributes(): array
    {
        return self::TRANSLATED_FIELDS;
    }

    public function approvableSnapshot(?string $locale): array
    {
        $translation = $this->translations()->where('locale', $locale)->first();

        return $translation?->only($this->approvableAttributes()) ?? [];
    }

    public function applyApprovedAttributes(array $attributes, ?string $locale): void
    {
        $this->translations()->updateOrCreate(['locale' => $locale], $attributes);

        $this->forceFill(['lock_version' => $this->lock_version + 1])->save();
    }

    public function approvalPermission(array $attributes): Permission
    {
        return array_diff(array_keys($attributes), ['seo_title', 'seo_description']) === []
            ? Permission::SeoApprove
            : Permission::ContentApprove;
    }
}
