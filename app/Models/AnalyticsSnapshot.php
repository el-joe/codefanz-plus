<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A cached pull of one GA4 / Search Console report for one day. Dashboards only ever show these rows, so nothing is
 * invented when the Google connection is missing or expired.
 */
#[Fillable(['source', 'report_key', 'date', 'dimensions', 'values', 'fetched_at'])]
class AnalyticsSnapshot extends Model
{
    public const SOURCE_GA4 = 'ga4';

    public const SOURCE_GSC = 'gsc';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'dimensions' => 'array',
            'values' => 'array',
            'fetched_at' => 'datetime',
        ];
    }
}
