<?php

namespace App\Models;

use App\Enums\SeoAuditStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One crawl of the site's own public URLs (from the sitemaps) with the issues it found.
 */
#[Fillable(['status', 'triggered_by', 'pages_crawled', 'health_score', 'summary', 'error', 'started_at', 'finished_at'])]
class SeoAudit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SeoAuditStatus::class,
            'pages_crawled' => 'integer',
            'health_score' => 'integer',
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SeoIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(SeoIssue::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
