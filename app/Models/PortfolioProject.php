<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\ContentStatus;
use App\Enums\Permission;
use App\Enums\ProjectType;
use App\Enums\ProjectVisibility;
use App\Models\Concerns\HasMediaCollections;
use App\Models\Concerns\HasTranslations;
use Database\Factories\PortfolioProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A delivered project (case study). Published + public projects mark their technologies as "Verified in projects".
 *
 * @method PortfolioProjectTranslation|null translation(?string $locale = null)
 * @method PortfolioProjectTranslation|null exactTranslation(string $locale)
 */
#[Fillable([
    'portfolio_category_id', 'industry_id', 'type', 'client_name', 'is_client_public', 'project_date',
    'live_url', 'android_url', 'ios_url', 'github_url', 'video_url', 'is_featured', 'visibility',
    'status', 'sort_order', 'published_at', 'created_by', 'updated_by',
])]
class PortfolioProject extends Model implements Approvable
{
    /** @use HasFactory<PortfolioProjectFactory> */
    use HasFactory, HasMediaCollections, HasTranslations, SoftDeletes;

    public const TRANSLATED_FIELDS = ['title', 'slug', 'summary', 'challenge', 'solution', 'results', 'key_facts', 'seo_title', 'seo_description'];

    /**
     * Hosts accepted for the project video (embedded privacy-friendly on the project page).
     */
    public const VIDEO_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lock_version' => 1,
        'type' => 'web',
        'status' => 'draft',
        'visibility' => 'public',
        'sort_order' => 0,
        'is_featured' => false,
        'is_client_public' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ContentStatus::class,
            'visibility' => ProjectVisibility::class,
            'is_featured' => 'boolean',
            'is_client_public' => 'boolean',
            'project_date' => 'date',
            'sort_order' => 'integer',
            'lock_version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Published projects, whether listed or reachable by direct link only.
     *
     * @param  Builder<PortfolioProject>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * Published projects that appear in listings, related lists and verification.
     *
     * @param  Builder<PortfolioProject>  $query
     */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('status', ContentStatus::Published)->where('visibility', ProjectVisibility::Public);
    }

    /**
     * @param  Builder<PortfolioProject>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('project_date')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<PortfolioCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PortfolioCategory::class, 'portfolio_category_id');
    }

    /**
     * @return BelongsTo<Industry, $this>
     */
    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    /**
     * @return BelongsToMany<Technology, $this>
     */
    public function technologies(): BelongsToMany
    {
        return $this->belongsToMany(Technology::class, 'portfolio_project_technology')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'portfolio_project_service')->withTimestamps();
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
    public function gallery(): MorphToMany
    {
        return $this->mediaIn('gallery');
    }

    public function coverImage(): ?Media
    {
        return $this->cover->first();
    }

    /**
     * Client name for visitors: null unless the client agreed to be named.
     */
    public function publicClientName(): ?string
    {
        return $this->is_client_public && filled($this->client_name) ? $this->client_name : null;
    }

    public function isPubliclyListed(): bool
    {
        return $this->status === ContentStatus::Published && $this->visibility === ProjectVisibility::Public;
    }

    public static function isAllowedVideoUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && in_array(mb_strtolower($parts['host'] ?? ''), self::VIDEO_HOSTS, true)
            && self::embedUrlFor($url) !== null;
    }

    /**
     * Privacy-enhanced embed URL for a YouTube or Vimeo link, or null when it cannot be recognised.
     */
    public static function embedUrlFor(string $url): ?string
    {
        $parts = parse_url($url);
        $host = mb_strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);

        $youtubeId = match (true) {
            $host === 'youtu.be' => $path,
            str_ends_with($host, 'youtube.com') && $path === 'watch' => is_string($query['v'] ?? null) ? $query['v'] : null,
            str_ends_with($host, 'youtube.com') && preg_match('#^(embed|shorts|live)/([^/]+)$#', $path, $matches) === 1 => $matches[2],
            default => null,
        };

        if ($youtubeId !== null && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $youtubeId) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$youtubeId;
        }

        if (str_ends_with($host, 'vimeo.com') && preg_match('#^(?:video/)?(\d{5,12})$#', $path, $matches) === 1) {
            return 'https://player.vimeo.com/video/'.$matches[1].'?dnt=1';
        }

        return null;
    }

    public function videoEmbedUrl(): ?string
    {
        return $this->video_url ? self::embedUrlFor($this->video_url) : null;
    }

    public function approvableVersion(): int
    {
        return (int) $this->lock_version;
    }

    /**
     * Text fields an AI/MCP proposal may change (the structured key facts list is edited by hand only).
     */
    public function approvableAttributes(): array
    {
        return ['title', 'slug', 'summary', 'challenge', 'solution', 'results', 'seo_title', 'seo_description'];
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
