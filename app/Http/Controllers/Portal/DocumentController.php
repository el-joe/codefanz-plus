<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Projects\StoreDocument;
use App\Enums\ClientVisibility;
use App\Enums\DocumentCategory;
use App\Http\Controllers\Admin\ProjectDocumentController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents (P10): every client-visible file of the current customer's projects, upload to one of their projects,
 * and the authorized download. Internal documents and other customers' files are a 404.
 */
class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
            'project' => ['nullable', 'string', 'max:26'],
            'shared' => ['nullable', 'in:client'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $projects = Project::query()->forCurrentCustomer()->orderBy('name')->get(['id', 'ulid', 'name']);

        $documents = Document::query()
            ->forCurrentCustomer()
            ->clientVisible()
            ->whereIn('project_id', $projects->modelKeys())
            ->with(['project:id,ulid,name', 'uploader:id,name'])
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['project'] ?? null, fn (Builder $query, string $ulid) => $query->where('project_id', $projects->firstWhere('ulid', $ulid)?->getKey() ?? 0))
            ->when($filters['shared'] ?? null, fn (Builder $query) => $query->where('uploaded_by_client', true))
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderByDesc('version')
            ->latest('id')
            ->get();

        return view('portal.documents.index', [
            'groups' => $documents->groupBy(fn (Document $document): string => $document->project_id.'|'.$document->title)
                ->sortByDesc(fn ($versions) => $versions->first()->created_at),
            'projects' => $projects,
            'filters' => $filters,
        ]);
    }

    public function store(StoreDocumentRequest $request, StoreDocument $store): RedirectResponse
    {
        $document = $store->handle(
            $request->project(),
            $request->file('file'),
            $request->validated('title'),
            DocumentCategory::from($request->validated('category')),
            ClientVisibility::Client,
            $request->user(),
            byClient: true,
        );

        return redirect()->route('portal.documents.index')->with('toast', __('Uploaded :title (version :version).', ['title' => $document->title, 'version' => $document->version]));
    }

    public function download(string $document): StreamedResponse
    {
        $document = Document::query()
            ->forCurrentCustomer()
            ->clientVisible()
            ->where('uuid', $document)
            ->whereHas('project')
            ->firstOrFail();

        return ProjectDocumentController::streamDownload($document);
    }
}
