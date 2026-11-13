<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\Permission;
use App\Enums\PostOrigin;
use App\Enums\PostStatus;
use App\Models\Concerns\HasMediaCollections;
use App\Models\Concerns\HasTranslations;
use App\Services\Blog\PostRevisions;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Blog article. Public only when Published with published_at in the past. Admin URLs use the ulid.
 *
 * AI changes go through change requests (Approvable) and never touch the status, so AI drafts cannot self-publish.
 *
 * @method PostTranslation|null translation(?string $locale = null)
 * @method PostTranslation|null exactTranslation(string $locale)
 */
#[Fillable(['author_id', 'post_category_id', 'status', 'origin', 'is_featured', 'is_single_language', 'scheduled_for', 'published_at', 'created_by', 'updated_by'])]
class Post extends Model implements Approvable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasMediaCollections, HasTranslations, HasUlids, SoftDeletes;

    public const TRANSLATED_FIELDS = ['title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'focus_keyword', 'canonical_url'];

    /**
     * Slugs that would collide with fixed blog routes.
     */
    public const RESERVED_SLUGS = ['feed', 'category', 'tag', 'page'];

    /**
     * Mirror database defaults so a freshly created instance reports the correct values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lock_version' => 1,
        'status' => 'draft',
        'origin' => 'human',
        'is_featured' => false,
        'is_single_language' => false,
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'origin' => PostOrigin::class,
            'is_featured' => 'boolean',
            'is_single_language' => 'boolean',
            'lock_version' => 'integer',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Publicly visible articles: published, with a publication date that has passed.
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeLatestPublished(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at !== null && $this->published_at->isPast();
    }

    public function isAiOrigin(): bool
    {
        return $this->origin?->isMachine() ?? false;
    }

    /**
     * Relations every article card needs.
     *
     * @return array<int|string, mixed>
     */
    public static function cardRelations(): array
    {
        return ['translations', 'category.translations', 'author', 'cover.variants'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * @return BelongsTo<PostCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * @return HasMany<PostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<PostChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(PostChecklistItem::class);
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
        return ['title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'focus_keyword'];
    }

    public function approvableSnapshot(?string $locale): array
    {
        $translation = $this->translations()->where('locale', $locale)->first();

        return $translation?->only($this->approvableAttributes()) ?? [];
    }

    /**
     * Applies text changes only: the status never changes here, so an approved AI proposal still has to go
     * through the editorial workflow before it is published. The body is sanitized by PostTranslation.
     */
    public function applyApprovedAttributes(array $attributes, ?string $locale): void
    {
        $this->translations()->updateOrCreate(['locale' => $locale], $attributes);

        $this->forceFill(['lock_version' => $this->lock_version + 1])->save();
        $this->unsetRelation('translations');

        app(PostRevisions::class)->record($this, null, 'change_request', 'AI / MCP change request');
    }

    public function approvalPermission(array $attributes): Permission
    {
        return array_diff(array_keys($attributes), ['seo_title', 'seo_description', 'focus_keyword']) === []
            ? Permission::SeoApprove
            : Permission::ContentApprove;
    }
}
