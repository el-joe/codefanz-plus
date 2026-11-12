<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Enums\SocialVariantStatus;
use Database\Factories\SocialPostVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The copy of a social post for one account, plus its delivery record (attempts, idempotency key, external id,
 * published URL and the platform response). The idempotency key is created once and never changes.
 */
#[Fillable([
    'social_post_id', 'social_account_id', 'platform', 'text', 'hashtags', 'first_comment', 'include_media',
    'ai_generation_id', 'status', 'idempotency_key', 'attempts', 'platform_state', 'external_id', 'published_url',
    'platform_response', 'last_error', 'published_at', 'exported_by', 'exported_at', 'metrics_error', 'metrics_checked_at',
])]
class SocialPostVariant extends Model
{
    /** @use HasFactory<SocialPostVariantFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'include_media' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $variant): void {
            $variant->idempotency_key ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => SocialVariantStatus::class,
            'include_media' => 'boolean',
            'attempts' => 'integer',
            'platform_state' => 'array',
            'platform_response' => 'array',
            'published_at' => 'datetime',
            'exported_at' => 'datetime',
            'metrics_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SocialPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    /**
     * @return BelongsTo<SocialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<AiGeneration, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class, 'ai_generation_id');
    }

    /**
     * @return HasMany<SocialMetricSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(SocialMetricSnapshot::class)->latest('captured_at');
    }

    /**
     * @return HasOne<SocialMetricSnapshot, $this>
     */
    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(SocialMetricSnapshot::class)->latestOfMany('captured_at');
    }

    /**
     * Hashtags normalised to "#tag" words separated by spaces.
     *
     * @return list<string>
     */
    public function hashtagList(): array
    {
        return self::parseHashtags((string) $this->hashtags);
    }

    /**
     * @return list<string>
     */
    public static function parseHashtags(string $value): array
    {
        return collect(preg_split('/[\s,]+/u', $value) ?: [])
            ->map(fn (string $tag): string => ltrim(trim($tag), '#'))
            ->filter(fn (string $tag): bool => $tag !== '')
            ->map(fn (string $tag): string => '#'.$tag)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The final text sent to the platform: caption, hashtags and — where the platform shows it in the text — the
     * tracked link.
     */
    public function composedText(?SocialPost $post = null): string
    {
        $post ??= $this->post;

        return self::compose($this->platform, (string) $this->text, (string) $this->hashtags, $post->trackedLink($this->platform->utmSource()));
    }

    public static function compose(SocialPlatform $platform, string $text, string $hashtags, ?string $link): string
    {
        $parts = [trim($text)];
        $tags = self::parseHashtags($hashtags);

        if ($tags !== []) {
            $parts[] = implode(' ', $tags);
        }

        if ($link !== null && $platform->linkPlacement() === 'text') {
            $parts[] = $link;
        }

        return implode("\n\n", array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * Length as the platform counts it (X counts every link as 23 characters).
     */
    public static function measure(SocialPlatform $platform, string $composed): int
    {
        if ($platform->linkWeight() === null) {
            return mb_strlen($composed);
        }

        $withoutLinks = (string) preg_replace('~https?://\S+~u', '', $composed, -1, $links);

        return mb_strlen($withoutLinks) + $links * $platform->linkWeight();
    }

    /**
     * Library images that go out with this variant.
     *
     * @return Collection<int, Media>
     */
    public function mediaItems(?SocialPost $post = null): Collection
    {
        $post ??= $this->post;

        return $this->include_media ? $post->mediaIn(SocialPost::MEDIA_COLLECTION)->get() : collect();
    }
}
