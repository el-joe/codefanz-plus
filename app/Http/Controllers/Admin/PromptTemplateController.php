<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Ai\GenerateContent;
use App\Actions\Ai\SavePromptTemplate;
use App\Enums\Permission;
use App\Enums\PromptPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePromptTemplateRequest;
use App\Models\Language;
use App\Models\PromptTemplate;
use App\Services\Ai\AiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Prompt library (ai.manage): CRUD with variable validation, version history and a dry-run test against the
 * configured provider (logged as usage, never used for content).
 */
class PromptTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.prompts.index', [
            'templates' => PromptTemplate::query()->withCount('generations')->orderBy('purpose')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.prompts.form', [
            'template' => new PromptTemplate(['variables' => [], 'is_active' => true]),
            'purposes' => $this->purposes(),
            'languages' => Language::active(),
            'versions' => collect(),
            'dryRuns' => collect(),
        ]);
    }

    public function store(SavePromptTemplateRequest $request, SavePromptTemplate $save): RedirectResponse
    {
        $template = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.prompts.edit', $template)->with('toast', __('Prompt saved.'));
    }

    public function edit(PromptTemplate $template, AiGateway $gateway): View
    {
        return view('admin.prompts.form', [
            'template' => $template,
            'purposes' => $this->purposes(),
            'languages' => Language::active(),
            'versions' => $template->versions()->with('creator')->latest('version')->get(),
            'dryRuns' => $template->generations()->where('is_dry_run', true)->latest('id')->limit(5)->get(),
            'reason' => $gateway->unavailableReason($template->purpose->feature(), request()->user()->getKey()),
        ]);
    }

    public function update(SavePromptTemplateRequest $request, PromptTemplate $template, SavePromptTemplate $save): RedirectResponse
    {
        $save->handle($template, $request->validated(), $request->user());

        return redirect()->route('admin.prompts.edit', $template)->with('toast', __('Prompt saved.'));
    }

    /**
     * Dry run with sample inputs: a real provider call, logged in ai_usage_logs and kept as a dry-run generation.
     */
    public function test(Request $request, PromptTemplate $template, GenerateContent $generate): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AiUse->value), 403);

        $validated = $request->validate([
            'inputs' => ['nullable', 'array'],
            'inputs.*' => ['nullable', 'string', 'max:20000'],
            'test_locale' => ['required', 'string', Rule::exists('languages', 'code')->where('is_active', true)],
        ]);

        $generation = $generate->handle(
            template: $template,
            user: $request->user(),
            inputs: (array) ($validated['inputs'] ?? []),
            locales: [$validated['test_locale']],
            dryRun: true,
        );

        return redirect()->route('admin.ai-studio.generations.show', $generation)->with('toast', __('Test run finished. It was logged as usage and cannot be published.'));
    }

    /**
     * @return array<string, string>
     */
    private function purposes(): array
    {
        return collect(PromptPurpose::cases())->mapWithKeys(fn (PromptPurpose $purpose): array => [$purpose->value => $purpose->label()])->all();
    }
}
