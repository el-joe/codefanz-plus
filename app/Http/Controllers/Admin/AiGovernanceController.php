<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiFeature;
use App\Enums\ChangeRequestStatus;
use App\Enums\KillSwitchKey;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Services\Ai\AiBudget;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiUsageLimits;
use App\Services\KillSwitches;
use Illuminate\View\View;

/**
 * AI governance (automation.manage): one place to see and pause every AI feature (assistant, content studio, SEO
 * suggestions, social copy, radar) and MCP writes, with this month's usage against each cap. Toggles reuse the
 * audited emergency-control endpoint.
 */
class AiGovernanceController extends Controller
{
    public function __invoke(KillSwitches $killSwitches, AiGateway $gateway, AiBudget $budget, AiUsageLimits $limits): View
    {
        $features = collect(AiFeature::cases())->map(fn (AiFeature $feature): array => [
            'feature' => $feature,
            'switch' => $feature->killSwitch(),
            'paused' => $killSwitches->isActive($feature->killSwitch()),
            'reason' => $gateway->unavailableReason($feature),
            'tokens' => $limits->featureTokensThisMonth($feature),
            'cap' => $limits->featureMonthlyTokenLimit($feature),
        ]);

        $global = collect([KillSwitchKey::AiAutomation, KillSwitchKey::McpWrites, KillSwitchKey::SocialPublishing])
            ->map(fn (KillSwitchKey $key): array => ['key' => $key, 'active' => $killSwitches->isActive($key)]);

        return view('admin.ai-governance', [
            'features' => $features,
            'global' => $global,
            'provider' => $gateway->provider(),
            'spentMinor' => $budget->spentMinor(),
            'limitMinor' => $budget->limitMinor(),
            'userDailyRequests' => $limits->userDailyRequestLimit(),
            'userMonthlyTokens' => $limits->userMonthlyTokenLimit(),
            'pendingAi' => ChangeRequest::query()->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview])->where('origin', '!=', 'human')->count(),
        ]);
    }
}
