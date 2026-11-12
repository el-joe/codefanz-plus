<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\PricingModel;
use App\Enums\ProductAvailability;
use App\Models\Concerns\HasMediaCollections;
use App\Models\Concerns\HasTranslations;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A ready-made software product. Licensing models are listed without prices (quotes are preliminary).
 *
 * @method ProductTranslation|null translation(?string $locale = null)
 * @method ProductTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['icon', 'pricing_models', 'availability', 'demo_enabled', 'docs_url', 'status', 'sort_order', 'published_at', 'created_by', 'updated_by'])]
class Product extends Model implements Approvable
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasMediaCollections, HasTranslations, SoftDeletes;

    public const TRANSLATED_FIELDS = ['name', 'slug', 'tagline', 'description', 'features', 'licensing_notes', 'seo_title', 'seo_description'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lock_version' => 1,
        'availability' => 'available',
        'status' => 'draft',
        'demo_enabled' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_models' => AsEnumCollection::of(PricingModel::class),
            'availability' => ProductAvailability::class,
            'status' => ContentStatus::class,
            'demo_enabled' => 'boolean',
            'sort_order' => 'integer',
            'lock_version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProductVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class)->latestFirst();
    }

    /**
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

    /**
     * @return MorphToMany<Media, $this>
     */
    public function screenshots(): MorphToMany
    {
        return $this->mediaIn('screenshots');
    }

    public function coverImage(): ?Media
    {
        return $this->cover->first();
    }

    /**
     * Newest version (expects `versions` eager-loaded).
     */
    public function latestVersion(): ?ProductVersion
    {
        return $this->versions->first();
    }

    public function approvableVersion(): int
    {
        return (int) $this->lock_version;
    }

    /**
     * Text fields an AI/MCP proposal may change (the structured features list is edited by hand only).
     */
    public function approvableAttributes(): array
    {
        return ['name', 'slug', 'tagline', 'description', 'licensing_notes', 'seo_title', 'seo_description'];
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
