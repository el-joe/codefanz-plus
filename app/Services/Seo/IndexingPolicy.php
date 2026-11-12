<?php

namespace App\Services\Seo;

use App\Services\Settings;

/**
 * Settings → SEO "block_indexing": block (robots.txt `Disallow: /` + `X-Robots-Tag: noindex` on every response),
 * allow, or unset — which blocks everywhere except production so staging copies never get indexed.
 */
class IndexingPolicy
{
    public function __construct(private Settings $settings) {}

    public function blocksIndexing(): bool
    {
        return match ($this->settings->get('seo', 'block_indexing')) {
            'block' => true,
            'allow' => false,
            default => ! app()->isProduction(),
        };
    }
}
