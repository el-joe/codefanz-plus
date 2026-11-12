<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiFeature;
use App\Enums\AiUsageStatus;
use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Services\Ai\AiBudget;
use App\Services\Ai\AiGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * AI usage (settings.manage): monthly tokens and estimated cost per feature and model, and the budget status.
 * Spend is an org-wide billing concern next to the provider keys and budget, so it sits with settings.manage
 * rather than ai.use (which content, SEO and social roles hold to use AI features).
 */
class AiUsageController extends Controller
{
    public function __invoke(Request $request, AiBudget $budget, AiGateway $gateway): View
    {
        $month = $this->month($request->query('month'));
        $isCurrentMonth = $month->isSameMonth(now());

        $totals = fn ($query) => $query->selectRaw('count(*) as calls')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [AiUsageStatus::Failed->value])
            ->selectRaw('coalesce(sum(input_tokens), 0) as input_tokens')
            ->selectRaw('coalesce(sum(output_tokens), 0) as output_tokens')
            ->selectRaw('coalesce(sum(estimated_cost_minor), 0) as cost_minor');

        $byFeature = $totals(AiUsageLog::query()->inMonth($month)->select('feature'))->groupBy('feature')->get()
            ->map(fn (AiUsageLog $row): array => [
                'label' => $row->feature->label(),
                'calls' => (int) $row->getAttribute('calls'),
                'failed' => (int) $row->getAttribute('failed'),
                'input_tokens' => (int) $row->getAttribute('input_tokens'),
                'output_tokens' => (int) $row->getAttribute('output_tokens'),
                'cost_minor' => (float) $row->getAttribute('cost_minor'),
            ]);

        $byModel = $totals(AiUsageLog::query()->inMonth($month)->select(['provider', 'model']))->groupBy('provider', 'model')->orderBy('provider')->get()
            ->map(fn (AiUsageLog $row): array => [
                'label' => $row->provider.' · '.$row->model,
                'calls' => (int) $row->getAttribute('calls'),
                'failed' => (int) $row->getAttribute('failed'),
                'input_tokens' => (int) $row->getAttribute('input_tokens'),
                'output_tokens' => (int) $row->getAttribute('output_tokens'),
                'cost_minor' => (float) $row->getAttribute('cost_minor'),
            ]);

        $limit = $budget->limitMinor();
        $spentThisMonth = $budget->spentMinor();

        return view('admin.ai-usage', [
            'month' => $month,
            'isCurrentMonth' => $isCurrentMonth,
            'months' => collect(range(0, 11))->map(fn (int $offset): Carbon => now()->startOfMonth()->subMonths($offset)),
            'byFeature' => $byFeature,
            'byModel' => $byModel,
            'monthCost' => (float) $byFeature->sum('cost_minor'),
            'limitMinor' => $limit,
            'spentMinor' => $spentThisMonth,
            'percent' => $limit > 0 ? min(100, (int) round($spentThisMonth / $limit * 100)) : 100,
            'provider' => $gateway->provider(),
            'assistantReason' => $gateway->unavailableReason(AiFeature::ProjectAssistant),
            'failures' => AiUsageLog::query()->inMonth($month)->where('status', AiUsageStatus::Failed)->latest('id')->limit(10)->get(),
        ]);
    }

    private function month(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            $month = Carbon::createFromFormat('!Y-m', $value);

            if ($month !== null && $month->lte(now())) {
                return $month->startOfMonth();
            }
        }

        return now()->startOfMonth();
    }
}
