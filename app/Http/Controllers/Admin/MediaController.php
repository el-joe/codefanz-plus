<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Media\StoreUploadedMedia;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Models\Media;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString();

        return view('admin.media.index', [
            'media' => Media::query()
                ->with('variants')
                ->withCount('usages')
                ->when($filter === 'unused', fn ($query) => $query->unused())
                ->when($filter === 'failed', fn ($query) => $query->where('status', MediaStatus::Failed))
                ->orderByDesc('id')
                ->paginate(24)
                ->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function store(StoreMediaRequest $request, StoreUploadedMedia $store): RedirectResponse
    {
        $count = 0;

        foreach ($request->file('files') as $file) {
            $store->handle($file, $request->user(), $request->validated('folder'));
            $count++;
        }

        return redirect()->route('admin.media.index')
            ->with('toast', trans_choice(':count file uploaded. Optimization runs in the background.|:count files uploaded. Optimization runs in the background.', $count));
    }

    public function show(Media $media): View
    {
        $media->load(['variants', 'usages', 'uploader']);

        return view('admin.media.show', ['media' => $media]);
    }

    public function update(UpdateMediaRequest $request, Media $media, AuditLogger $audit): RedirectResponse
    {
        $before = $media->only(['alt', 'folder']);
        $media->update(['alt' => array_filter($request->validated('alt')), 'folder' => $request->validated('folder')]);

        $audit->record(operation: 'media.updated', auditable: $media, user: $request->user(), before: $before, after: $media->only(['alt', 'folder']));

        return back()->with('toast', __('Media details saved.'));
    }

    public function destroy(Request $request, Media $media, AuditLogger $audit): RedirectResponse
    {
        if ($media->usages()->exists()) {
            return back()->withErrors(['media' => __('This file is still used on the site. Replace it there first.')]);
        }

        $media->delete();
        $audit->record(operation: 'media.deleted', auditable: $media, user: $request->user(), before: ['name' => $media->original_name]);

        return redirect()->route('admin.media.index')->with('toast', __('File moved to trash.'));
    }
}
