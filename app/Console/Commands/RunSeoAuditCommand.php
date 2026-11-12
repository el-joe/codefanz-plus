<?php

namespace App\Console\Commands;

use App\Enums\SeoAuditStatus;
use App\Jobs\RunSeoAudit;
use App\Models\SeoAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:audit {--sync : Crawl now instead of queueing the audit}')]
#[Description('Crawl the public sitemap URLs and record SEO issues (queued by default)')]
class RunSeoAuditCommand extends Command
{
    public function handle(): int
    {
        $audit = SeoAudit::create(['status' => SeoAuditStatus::Queued]);

        if ($this->option('sync')) {
            RunSeoAudit::dispatchSync($audit);
            $audit->refresh();
            $this->info("SEO audit #{$audit->id}: {$audit->status->value}, {$audit->pages_crawled} page(s), health ".($audit->health_score ?? '—').'.');

            return $audit->status === SeoAuditStatus::Completed ? self::SUCCESS : self::FAILURE;
        }

        RunSeoAudit::dispatch($audit);
        $this->info("SEO audit #{$audit->id} queued.");

        return self::SUCCESS;
    }
}
