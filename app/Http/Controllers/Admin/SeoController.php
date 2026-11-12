<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Seo\ProposeSeoFix;
use App\Actions\Seo\UpdateSeoMeta;
use App\Enums\SeoAuditStatus;
use App\Enums\SeoIssueType;
use App\Enums\SeoSeverity;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RobotsController;
use App\Http\Requests\Admin\UpdateSeoMetaRequest;
use App\Jobs\RunSeoAudit;
use App\Models\Language;
use App\Models\SeoAudit;
use App\Models\SeoIssue;
use App\Services\AuditLogger;
use App\Services\Seo\IndexingPolicy;
use App\Services\Seo\SeoInventory;
use App\Services\Seo\SeoTargets;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A12 SEO management (seo.manage): audit dashboard, meta editor and sitemap/robots overview.
 */
class SeoController extends Controller
{
    public function audit(Request $request, ProposeSeoFix $aiFix, SeoTargets $targets): View
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', Rule::in(array_keys(SeoIssueType::categories()))],
            'severity' => ['nullable', Rule::enum(SeoSeverity::class)],
        ]);

        $latest = SeoAudit::query()->where('status', SeoAuditStatus::Completed)->latest('id')->first();
        $inProgress = SeoAudit::query()->whereIn('status', [SeoAuditStatus::Queued, SeoAuditStatus::Running])->where('created_at', '>=', now()->subHours(2))->latest('id')->first();
        $lastFailed = SeoAudit::query()->where('status', SeoAuditStatus::Failed)->where('id', '>', $latest?->id ?? 0)->latest('id')->first();

        $issues = $latest
            ? $latest->issues()
                ->with('seoable')
                ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
                ->when($filters['severity'] ?? null, fn ($query, string $severity) => $query->where('severity', $severity))
                ->orderByRaw("field(severity, 'error', 'warning', 'notice')")
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString()
            : null;

        $categoryCounts = $latest ? $latest->issues()->toBase()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category') : collect();

        return view('admin.seo.audit', [
            'latest' => $latest,
            'inProgress' => $inProgress,
            'lastFailed' => $lastFailed,
            'issues' => $issues,
            'categoryCounts' => $categoryCounts,
            'filters' => $filters,
            'aiFix' => $aiFix,
            'targets' => $targets,
        ]);
    }

    public function runAudit(Request $request, AuditLogger $audit): RedirectResponse
    {
        $running = SeoAudit::query()->whereIn('status', [SeoAuditStatus::Queued, SeoAuditStatus::Running])->where('created_at', '>=', now()->subHours(2))->exists();

        if ($running) {
            return back()->with('toast', __('An audit is already running.'));
        }

        $seoAudit = SeoAudit::create(['status' => SeoAuditStatus::Queued, 'triggered_by' => $request->user()->getKey()]);
        $audit->record(operation: 'seo.audit_started', auditable: $seoAudit, user: $request->user());
        RunSeoAudit::dispatch($seoAudit);

        return redirect()->route('admin.seo.audit')->with('toast', __('SEO audit started. Results appear here when the crawl finishes.'));
    }

    public function meta(Request $request, SeoInventory $inventory, SeoTargets $targets): View
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['route', ...$targets->types()])],
            'locale' => ['nullable', 'string', Rule::in(Language::active()->keys()->all())],
            'q' => ['nullable', 'string', 'max:100'],
            'score' => ['nullable', Rule::in(['low', 'medium', 'high'])],
        ]);

        $rows = $inventory->rows($filters['type'] ?? null, $filters['locale'] ?? null)
            ->when($filters['q'] ?? null, fn ($rows, string $term) => $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['name'].' '.$row['url'].' '.$row['effective_title']), mb_strtolower($term))))
            ->when($filters['score'] ?? null, fn ($rows, string $band) => $rows->filter(fn (array $row): bool => match ($band) {
                'low' => $row['score']['score'] < 50,
                'medium' => $row['score']['score'] >= 50 && $row['score']['score'] < 80,
                default => $row['score']['score'] >= 80,
            }))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.seo.meta', [
            'rows' => $paginator,
            'filters' => $filters,
            'types' => ['route' => __('Site pages'), ...collect($targets->types())->mapWithKeys(fn (string $type): array => [$type => $targets->label($type)])->all()],
            'averageScore' => $rows->isEmpty() ? null : (int) round($rows->avg(fn (array $row): int => $row['score']['score'])),
        ]);
    }

    public function updateMeta(UpdateSeoMetaRequest $request, UpdateSeoMeta $update): RedirectResponse
    {
        $update->handle($request->validated(), $request->user());

        return back()->with('toast', __('SEO settings saved.'));
    }

    public function sitemap(SitemapBuilder $sitemaps, IndexingPolicy $indexing, RobotsController $robots): View
    {
        return view('admin.seo.sitemap', [
            'children' => $sitemaps->childNames(),
            'urlCount' => count($sitemaps->allUrls()),
            'blocksIndexing' => $indexing->blocksIndexing(),
            'robots' => $robots($indexing)->getContent(),
        ]);
    }

    public function aiFix(Request $request, SeoIssue $issue, ProposeSeoFix $propose): RedirectResponse
    {
        $changeRequest = $propose->handle($issue, $request->user());

        return redirect()->route('admin.approvals.show', $changeRequest)->with('toast', __('AI suggestion sent to the approval queue. Nothing changes until it is approved.'));
    }
}
