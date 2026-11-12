<?php

namespace App\Models;

use App\Enums\SeoIssueType;
use App\Enums\SeoSeverity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A problem found on one URL during an SEO audit. `seoable`/`route_key` point at what to edit to fix it.
 */
#[Fillable(['seo_audit_id', 'type', 'category', 'severity', 'url', 'locale', 'seoable_type', 'seoable_id', 'route_key', 'details', 'resolved_at'])]
class SeoIssue extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SeoIssueType::class,
            'severity' => SeoSeverity::class,
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SeoAudit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'seo_audit_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
