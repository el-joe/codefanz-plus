<?php

namespace App\Jobs;

use App\Models\SeoAudit;
use App\Services\Seo\SeoCrawler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs one SEO audit crawl in the background.
 */
class RunSeoAudit implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public SeoAudit $audit) {}

    public function handle(SeoCrawler $crawler): void
    {
        $crawler->run($this->audit);
    }
}
