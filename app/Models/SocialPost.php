<?php

namespace App\Models;

use App\Enums\PostOrigin;
use App\Enums\SocialPostStatus;
use App\Models\Concerns\HasMediaCollections;
use Carbon\CarbonInterface;
use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One social post with a variant per target account. Images come from the media library (collection "social",
 * alt text per image in media_alt). Status changes only through App\Actions\Social\SocialPostWorkflow; the publish
 * queue only picks up approved, scheduled posts.
 */
#[Fillable([
    'title', 'campaign', 'locale', 'status', 'origin', 'link_url', 'utm', 'media_alt', 'planned_at', 'scheduled_at', 'published_at',
    'author_id', 'generated_by', 'submitted_by', 'submitted_at', 'reviewed_by', 'approved_at', 'review_note',
])]
class SocialPost extends Model
{
    /** @use HasFactory<SocialPostFactory> */
    use HasFactory, HasMediaCollections, HasUlids, SoftDeletes;

    public const MEDIA_COLLECTION = 'social';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'origin' => 'human',
        'locale' => 'en',
        'lock_version' => 1,
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
            'status' => SocialPostStatus::class,
            'origin' => PostOrigin::class,
            'utm' => 'array',
            'media_alt' => 'array',
            'planned_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return HasMany<SocialPostVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(SocialPostVariant::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isAiOrigin(): bool
    {
        return $this->origin?->isMachine() ?? false;
    }

    /**
     * The person who generated an AI post (or its author) can never approve or schedule it.
     */
    public function isGeneratedBy(User $user): bool
    {
        return $this->isAiOrigin() && in_array($user->getKey(), array_filter([$this->generated_by, $this->author_id]), true);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null && $this->reviewed_by !== null;
    }

    /**
     * Where the post sits on the calendar: its schedule, its publication, or the planned time of a draft.
     */
    public function calendarAt(): ?CarbonInterface
    {
        return $this->scheduled_at ?? $this->published_at ?? $this->planned_at;
    }

    public function altFor(int $mediaId): string
    {
        return (string) (($this->media_alt ?? [])[(string) $mediaId] ?? '');
    }

    /**
     * The post link with UTM parameters for one platform (utm_source = the platform).
     */
    public function trackedLink(string $source): ?string
    {
        if (blank($this->link_url)) {
            return null;
        }

        $utm = array_filter([
            'utm_source' => $source,
            'utm_medium' => $this->utm['medium'] ?? null,
            'utm_campaign' => $this->utm['campaign'] ?? null,
            'utm_content' => $this->utm['content'] ?? null,
        ], fn ($value): bool => filled($value));

        if (count($utm) <= 1) {
            return $this->link_url;
        }

        $fragment = '';
        $url = (string) $this->link_url;

        if (str_contains($url, '#')) {
            [$url, $fragment] = explode('#', $url, 2);
            $fragment = '#'.$fragment;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($utm, '', '&', PHP_QUERY_RFC3986).$fragment;
    }
}
