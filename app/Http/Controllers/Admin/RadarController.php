<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Radar\ChangeRadarProjectStatus;
use App\Actions\Radar\ConvertRadarProjectToLead;
use App\Actions\Radar\FetchRadarPlatform;
use App\Actions\Radar\ScoreRadarProjectWithAi;
use App\Enums\AiFeature;
use App\Enums\Permission;
use App\Enums\RadarProjectStatus;
use App\Exceptions\AiUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConvertRadarProjectRequest;
use App\Models\Language;
use App\Models\RadarFilter;
use App\Models\RadarPlatform;
use App\Models\RadarProject;
use App\Services\Ai\AiGateway;
use App\Services\AuditLogger;
use App\Services\Radar\RadarAnalytics;
use App\Services\Radar\RadarFilterMatcher;
use App\Services\Radar\RadarScorer;
use App\Services\Radar\RadarSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * B09 Freelance Projects Radar: tiles, connector statuses, filter-profile tabs, the scored project list and the
 * project page (rule breakdown beside the AI score, status, proposal drafts, convert to lead). Viewing needs
 * radar.view; every change needs radar.manage.
 */
class RadarController extends Controller
{
    public function index(Request $request, RadarFilterMatcher $matcher, RadarAnalytics $analytics, RadarSettings $settings): View
    {
        $filters = $request->validate([
            'profile' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(RadarProjectStatus::class)],
            'platform' => ['nullable', 'string', 'max:40'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['score', 'newest'])],
        ]);

        $profiles = RadarFilter::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $profile = isset($filters['profile']) ? $profiles->firstWhere('id', (int) $filters['profile']) : null;

        $query = RadarProject::query()->with(['platform', 'alerts', 'aiEvaluation']);

        if ($profile !== null) {
            $matcher->apply($query, $profile);
        }

        $projects = $query
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status), fn ($query) => $query->where('status', '!=', RadarProjectStatus::Ignored))
            ->when($filters['platform'] ?? null, fn ($query, $key) => $query->whereIn('radar_platform_id', RadarPlatform::query()->where('key', $key)->select('id')))
            ->when(isset($filters['min_score']), fn ($query) => $query->where('score', '>=', (int) $filters['min_score']))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($inner) => $inner->where('title', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('description', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->when(($filters['sort'] ?? 'score') === 'newest', fn ($query) => $query->latest('id'), fn ($query) => $query->orderByDesc('score')->latest('id'))
            ->paginate(20)
            ->withQueryString();

        return view('admin.radar.index', [
            'projects' => $projects,
            'profiles' => $profiles,
            'profile' => $profile,
            'filters' => $filters,
            'platforms' => RadarPlatform::query()->orderBy('id')->get(),
            'tiles' => $analytics->tiles(),
            'thresholds' => $settings->thresholds(),
        ]);
    }

    public function show(Request $request, RadarProject $project, AiGateway $gateway, RadarScorer $scorer): View
    {
        $project->load(['platform', 'ruleEvaluation', 'aiEvaluation', 'proposals.author', 'alerts', 'lead']);

        return view('admin.radar.show', [
            'project' => $project,
            'matchingSkills' => $scorer->matchingSkills($project),
            'aiReason' => $gateway->unavailableReason(AiFeature::Radar, $request->user()->getKey()),
            'statuses' => RadarProjectStatus::options(),
            'locales' => Language::active()->map(fn (array $language): string => $language['name'])->all(),
            'canConvert' => $request->user()->can(Permission::LeadsManage->value),
            'placeholderEmail' => $project->platform?->key.'-'.preg_replace('/[^a-z0-9-]/i', '', $project->external_id).'@radar.invalid',
        ]);
    }

    public function status(Request $request, RadarProject $project, ChangeRadarProjectStatus $change): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(RadarProjectStatus::class)]]);

        $change->handle($project, RadarProjectStatus::from($data['status']), $request->user());

        return back()->with('toast', __('Status updated.'));
    }

    public function aiScore(Request $request, RadarProject $project, ScoreRadarProjectWithAi $score): RedirectResponse
    {
        try {
            $score->handle($project, $request->user(), force: true);
        } catch (AiUnavailable $exception) {
            return back()->with('toast', __('AI score unavailable: :reason', ['reason' => $exception->reason->label()]));
        }

        return back()->with('toast', __('AI score added. The rule score is unchanged.'));
    }

    public function convert(ConvertRadarProjectRequest $request, RadarProject $project, ConvertRadarProjectToLead $convert): RedirectResponse
    {
        $lead = $convert->handle($project, $request->validated(), $request->user());

        return to_route('admin.leads.show', $lead)->with('toast', __('Lead :reference created from the radar project.', ['reference' => $lead->reference]));
    }

    public function fetch(Request $request, RadarPlatform $platform, FetchRadarPlatform $fetch, AuditLogger $audit): RedirectResponse
    {
        $result = $fetch->handle($platform, force: true);

        $audit->record(operation: 'radar.fetch_requested', auditable: $platform, user: $request->user(), after: $result, result: $result['status'] === 'ok' ? 'success' : 'failure');

        return back()->with('toast', match ($result['status']) {
            'ok' => __(':platform: :created new, :updated updated, :unchanged unchanged.', ['platform' => $platform->name, 'created' => $result['created'] ?? 0, 'updated' => $result['updated'] ?? 0, 'unchanged' => $result['unchanged'] ?? 0]),
            'error' => __(':platform fetch failed: :error', ['platform' => $platform->name, 'error' => $result['error'] ?? '']),
            'needs_credentials' => __(':platform needs credentials in Settings → Freelance Radar.', ['platform' => $platform->name]),
            'rate_limited' => __(':platform reached its hourly request limit. Try again later.', ['platform' => $platform->name]),
            'paused' => __('Freelance Radar collection is paused by an emergency control.'),
            'disabled' => __(':platform is switched off in the radar.', ['platform' => $platform->name]),
            default => __(':platform has no API connector to run.', ['platform' => $platform->name]),
        });
    }
}
