<?php

namespace App\Console\Commands;

use App\Actions\Payments\RecomputeMilestonePaid;
use App\Enums\ActorType;
use App\Models\Milestone;
use App\Models\Payment;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('payments:reconcile {--milestone= : Only this milestone (ulid)}')]
#[Description('Recompute every milestone paid amount from the payments ledger and report differences')]
class ReconcileMilestonePaymentsCommand extends Command
{
    public function handle(RecomputeMilestonePaid $recompute, AuditLogger $audit): int
    {
        $query = Milestone::query()
            ->when($this->option('milestone'), fn ($query, string $ulid) => $query->where('ulid', $ulid))
            ->when(! $this->option('milestone'), fn ($query) => $query->where(fn ($query) => $query
                ->where('paid_minor', '>', 0)
                ->orWhereIn('id', Payment::query()->whereNotNull('milestone_id')->select('milestone_id'))));

        $checked = 0;
        $corrected = 0;

        $query->orderBy('id')->each(function (Milestone $milestone) use ($recompute, $audit, &$checked, &$corrected): void {
            $checked++;

            [$before, $after] = DB::transaction(function () use ($milestone, $recompute): array {
                Milestone::query()->lockForUpdate()->findOrFail($milestone->getKey());

                return $recompute->handle($milestone);
            });

            if ($before !== $after) {
                $corrected++;
                $this->warn("{$milestone->ulid}: {$before} → {$after} ({$milestone->currency} minor units)");
                $audit->record(operation: 'milestone.paid_reconciled', auditable: $milestone, actorType: ActorType::System, actorLabel: 'payments:reconcile', before: ['paid_minor' => $before], after: ['paid_minor' => $after]);
            }
        });

        $this->info("Checked {$checked} milestone(s); corrected {$corrected}.");

        return self::SUCCESS;
    }
}
