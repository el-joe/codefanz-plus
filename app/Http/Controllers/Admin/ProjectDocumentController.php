<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\DeleteDocument;
use App\Actions\Projects\StoreDocument;
use App\Enums\ClientVisibility;
use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Project;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Project documents in the admin: upload (new version when the title exists), share/unshare, delete and the
 * authorized download (always as an attachment, never inline).
 */
class ProjectDocumentController extends Controller
{
    public function store(StoreDocumentRequest $request, Project $project, StoreDocument $store): RedirectResponse
    {
        $document = $store->handle(
            $project,
            $request->file('file'),
            $request->validated('title'),
            DocumentCategory::from($request->validated('category')),
            ClientVisibility::from($request->validated('visibility')),
            $request->user(),
        );

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'documents'])
            ->with('toast', __('Uploaded :title (version :version).', ['title' => $document->title, 'version' => $document->version]));
    }

    public function update(Request $request, Project $project, Document $document, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $project);

        $visibility = ClientVisibility::from($request->validate(['visibility' => ['required', Rule::enum(ClientVisibility::class)]])['visibility']);
        $before = $document->visibility;
        $document->update(['visibility' => $visibility]);

        $audit->record(operation: 'document.visibility_changed', auditable: $document, user: $request->user(), before: ['visibility' => $before->value], after: ['visibility' => $visibility->value]);

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'documents'])->with('toast', __('Document visibility updated.'));
    }

    public function destroy(Request $request, Project $project, Document $document, DeleteDocument $delete): RedirectResponse
    {
        Gate::authorize('update', $project);

        $delete->handle($document, $request->user());

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'documents'])->with('toast', __('Document deleted.'));
    }

    public function download(Document $document): StreamedResponse
    {
        $project = Project::query()->withTrashed()->findOrFail($document->project_id);
        Gate::authorize('view', $project);

        return self::streamDownload($document);
    }

    public static function streamDownload(Document $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
