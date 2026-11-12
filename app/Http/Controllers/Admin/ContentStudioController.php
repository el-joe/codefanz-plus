<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Ai\GenerateContent;
use App\Enums\AiFeature;
use App\Enums\KillSwitchKey;
use App\Enums\PromptPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateContentRequest;
use App\Models\AiGeneration;
use App\Models\Language;
use App\Models\PromptTemplate;
use App\Services\Ai\AiBudget;
use App\Services\Ai\AiGateway;
use App\Services\Ai\StudioTargets;
use App\Services\KillSwitches;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AI Content Studio (B01 / B02, ai.use): template gallery, the brief form and generation. Results are private
 * AiGeneration records until the author turns them into a blog draft or a change request.
 */
class ContentStudioController extends Controller
{
    public function index(Request $request, AiGateway $gateway, AiBudget $budget, KillSwitches $killSwitches): View
    {
        $templates = PromptTemplate::query()->active()->orderBy('purpose')->orderBy('name')->get();

        return view('admin.ai-studio.index', [
            'templates' => $templates,
            'recent' => AiGeneration::query()
                ->with(['template', 'target.translations'])
                ->where('user_id', $request->user()->getKey())
                ->where('is_dry_run', false)
                ->latest('id')
                ->limit(10)
                ->get(),
            ...$this->status($gateway, $budget, $killSwitches, $request->user()->getKey()),
        ]);
    }

    public function create(Request $request, StudioTargets $targets, AiGateway $gateway, AiBudget $budget, KillSwitches $killSwitches): View
    {
        $template = PromptTemplate::query()->active()->where('key', (string) $request->query('template'))->firstOrFail();
        $targetType = $request->query('target_type');
        $targetType = is_string($targetType) && $targets->has($targetType) ? $targetType : null;

        return view('admin.ai-studio.create', [
            'template' => $template,
            'targetTypes' => $targets->options(),
            'targetType' => $targetType,
            'targetItems' => $targetType !== null ? $targets->items($targetType) : collect(),
            'selectedTargetId' => $request->integer('target_id') ?: null,
            'languages' => Language::active(),
            'reason' => $gateway->unavailableReason($template->purpose->feature(), $request->user()->getKey()),
            ...$this->status($gateway, $budget, $killSwitches, $request->user()->getKey()),
        ]);
    }

    public function store(GenerateContentRequest $request, GenerateContent $generate, StudioTargets $targets): RedirectResponse
    {
        $template = PromptTemplate::query()->active()->where('key', $request->validated('template'))->firstOrFail();
        $target = null;

        if ($template->purpose->needsTarget()) {
            $target = $targets->find((string) $request->validated('target_type'), (int) $request->validated('target_id'));

            if ($target === null) {
                return back()->withInput()->withErrors(['target_id' => __('Choose the website item to write for.')]);
            }
        }

        $translate = $template->purpose === PromptPurpose::Translation || ($target !== null && $request->boolean('translate_from_en'));

        $generation = $generate->handle(
            template: $template,
            user: $request->user(),
            inputs: (array) $request->validated('inputs', []),
            locales: $translate ? ['ar'] : (array) $request->validated('locales'),
            target: $target,
            translateFrom: $translate ? 'en' : null,
        );

        return redirect()->route('admin.ai-studio.generations.show', $generation)->with('toast', __('Draft generated. Review it before you use it.'));
    }

    /**
     * Banner data shared by the studio screens: provider, automation switch and this month's spend.
     *
     * @return array<string, mixed>
     */
    private function status(AiGateway $gateway, AiBudget $budget, KillSwitches $killSwitches, int $userId): array
    {
        return [
            'provider' => $gateway->provider(),
            'automationPaused' => $killSwitches->isActive(KillSwitchKey::AiAutomation) || $killSwitches->isActive(KillSwitchKey::AiContentStudio),
            'studioReason' => $gateway->unavailableReason(AiFeature::ContentStudio, $userId),
            'spentMinor' => $budget->spentMinor(),
            'limitMinor' => $budget->limitMinor(),
        ];
    }
}
