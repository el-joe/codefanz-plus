<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Ai\UseGeneration;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UseGenerationRequest;
use App\Models\AiGeneration;
use App\Models\Post;
use App\Services\Ai\StudioTargets;
use App\Services\Blog\TextDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Generation history and the result screen: current content next to the AI suggestion with a text diff, guardrail
 * flags, edits, then "Create blog draft" / "Send to approval" / "Save private draft" / "Discard".
 */
class AiGenerationController extends Controller
{
    public function index(Request $request): View
    {
        $seesAll = $request->user()->can(Permission::AiManage->value);

        return view('admin.ai-studio.generations', [
            'generations' => AiGeneration::query()
                ->with(['template', 'user', 'target.translations'])
                ->withCount('changeRequests')
                ->unless($seesAll, fn ($query) => $query->where('user_id', $request->user()->getKey()))
                ->latest('id')
                ->paginate(25),
            'seesAll' => $seesAll,
        ]);
    }

    public function show(AiGeneration $generation, StudioTargets $targets, TextDiff $diff): View
    {
        Gate::authorize('view', $generation);

        $generation->load(['template', 'user', 'post', 'target.translations', 'changeRequests']);
        $output = $generation->orderedOutput();
        $current = [];

        if ($generation->target !== null) {
            $fields = collect($output)->flatMap(fn (array $fields): array => array_keys($fields))->unique()->values()->all();
            $current = $targets->currentContent($generation->target, $fields, array_keys($output));
        }

        $diffs = [];

        foreach ($output as $locale => $fields) {
            foreach ($fields as $field => $value) {
                if (isset($current[$locale][$field])) {
                    $diffs[$locale][$field] = $diff->sideBySide(self::lines($current[$locale][$field]), self::lines((string) $value));
                }
            }
        }

        return view('admin.ai-studio.show', [
            'generation' => $generation,
            'output' => $output,
            'current' => $current,
            'diffs' => $diffs,
            'targetTitle' => $generation->target !== null ? $targets->titleOf($generation->target) : null,
            'targetLabel' => $generation->target_type !== null ? $targets->label($generation->target_type) : null,
            'canCreatePost' => request()->user()->can('create', Post::class),
        ]);
    }

    public function draft(UseGenerationRequest $request, AiGeneration $generation, UseGeneration $use): RedirectResponse
    {
        Gate::authorize('create', Post::class);

        $post = $use->createBlogDraft($generation, $request->edited(), $request->user());

        return redirect()->route('admin.posts.edit', $post)->with('toast', __('AI draft created. It stays a draft until an editor other than you approves it.'));
    }

    public function propose(UseGenerationRequest $request, AiGeneration $generation, UseGeneration $use): RedirectResponse
    {
        $changeRequests = $use->propose($generation, $request->edited(), $request->user());

        return redirect()->route('admin.ai-studio.generations.show', $generation)
            ->with('toast', __('Sent to the approval queue: :references.', ['references' => $changeRequests->pluck('reference')->implode(', ')]));
    }

    public function save(UseGenerationRequest $request, AiGeneration $generation, UseGeneration $use): RedirectResponse
    {
        $use->save($generation, $request->edited(), $request->user());

        return redirect()->route('admin.ai-studio.generations.show', $generation)->with('toast', __('Saved as a private draft.'));
    }

    public function discard(Request $request, AiGeneration $generation, UseGeneration $use): RedirectResponse
    {
        Gate::authorize('use', $generation);

        $use->discard($generation, $request->user());

        return redirect()->route('admin.ai-studio.index')->with('toast', __('Generation discarded.'));
    }

    /**
     * @return list<string>
     */
    public static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn (string $line): bool => $line !== ''));
    }
}
