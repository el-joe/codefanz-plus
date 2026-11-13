<?php

namespace App\Console\Commands;

use App\Services\Operations\ProductionReadiness;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-production')]
#[Description('Verify critical production settings (debug, https, mail, queue, storage link, indexing, scheduler…); exits non-zero on any failure')]
class CheckProductionCommand extends Command
{
    public function handle(ProductionReadiness $readiness): int
    {
        $results = $readiness->checks();

        $this->table(['Check', 'Status', 'Details'], array_map(fn (array $result): array => [
            $result['check'],
            match ($result['status']) {
                ProductionReadiness::PASS => '<info>PASS</info>',
                ProductionReadiness::WARN => '<comment>WARN</comment>',
                default => '<error>FAIL</error>',
            },
            $result['message'],
        ], $results));

        $failures = array_filter($results, fn (array $result): bool => $result['status'] === ProductionReadiness::FAIL);
        $warnings = array_filter($results, fn (array $result): bool => $result['status'] === ProductionReadiness::WARN);

        if ($failures !== []) {
            $this->error(count($failures).' critical production setting(s) are wrong. Fix them before going live.');

            return self::FAILURE;
        }

        $this->info('Production checks passed'.($warnings !== [] ? ' with '.count($warnings).' warning(s).' : '.'));

        return self::SUCCESS;
    }
}
