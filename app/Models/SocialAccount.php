<?php

namespace App\Models;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A LinkedIn page, Facebook page, Instagram professional account, X profile, TikTok or YouTube channel. Connected
 * accounts carry encrypted OAuth tokens; manual accounts only describe where staff post by hand.
 */
#[Fillable([
    'platform', 'name', 'handle', 'profile_url', 'external_id', 'status', 'access_token', 'refresh_token',
    'token_expires_at', 'refresh_expires_at', 'scopes', 'meta', 'last_verified_at', 'last_error', 'connected_by',
])]
#[Hidden(['access_token', 'refresh_token'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'status' => SocialAccountStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'scopes' => 'array',
            'meta' => 'array',
        ];
    }

    /**
     * @return HasMany<SocialPostVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(SocialPostVariant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /**
     * Whether approved posts go out through the platform API (otherwise they are prepared for manual export).
     */
    public function canPublishDirectly(): bool
    {
        return $this->status->canPublish()
            && $this->platform->supportsDirectPublishing()
            && filled($this->access_token)
            && filled($this->external_id);
    }

    public function tokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function displayName(): string
    {
        return $this->platform->shortLabel().' · '.$this->name;
    }

    /**
     * Drop every token and stop direct publishing.
     */
    public function forgetTokens(SocialAccountStatus $status): void
    {
        $this->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'refresh_expires_at' => null,
            'status' => $status,
        ])->save();
    }
}
