<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveService;
use App\Enums\ChangeRequestStatus;
use App\Enums\ContentStatus;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveServiceRequest;
use App\Models\ChangeRequest;
use App\Models\Language;
use App\Models\Media;
use App\Models\Service;
use App\Models\Technology;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Service::class);

        return view('admin.services.index', [
            'services' => Service::query()
                ->with(['translations', 'parent.translations'])
                ->orderBy('tier')
                ->ordered()
                ->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Service::class);

        return view('admin.services.form', [
            ...$this->formOptions(null),
            'service' => new Service,
            'pendingChanges' => collect(),
        ]);
    }

    public function store(SaveServiceRequest $request, SaveService $save): RedirectResponse
    {
        $service = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.services.edit', $service)->with('toast', __('Service created as a draft.'));
    }

    public function edit(Service $service): View
    {
        Gate::authorize('update', $service);

        $service->load(['translations', 'features.translations', 'faqs.translations', 'technologies', 'cover', 'children']);

        return view('admin.services.form', [
            ...$this->formOptions($service),
            'service' => $service,
            'pendingChanges' => ChangeRequest::query()
                ->whereMorphedTo('target', $service)
                ->whereIn('status', [ChangeRequestStatus::Pending, ChangeRequestStatus::UnderReview, ChangeRequestStatus::Approved])
                ->latest('id')
                ->get(),
        ]);
    }

    public function update(SaveServiceRequest $request, Service $service, SaveService $save): RedirectResponse
    {
        $save->handle($service, $request->validated(), $request->user());

        return redirect()->route('admin.services.edit', $service)->with('toast', __('Service saved.'));
    }

    public function publish(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $service);

        $publish = $service->status !== ContentStatus::Published;

        if ($publish && ! $service->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $service->forceFill([
            'status' => $publish ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $publish ? ($service->published_at ?? now()) : $service->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'service.published' : 'service.unpublished', auditable: $service, user: $request->user());

        return back()->with('toast', $publish ? __('Service published.') : __('Service moved back to draft.'));
    }

    public function destroy(Request $request, Service $service, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $service);

        if ($service->children()->exists()) {
            return back()->withErrors(['status' => __('Move or delete its sub-services first.')]);
        }

        $service->delete();
        $audit->record(operation: 'service.deleted', auditable: $service, user: $request->user());

        return redirect()->route('admin.services.index')->with('toast', __('Service moved to trash.'));
    }

    /**
     * @return array{parents: Collection<int, Service>, technologies: Collection<int, Technology>, images: Collection<int, Media>}
     */
    private function formOptions(?Service $service): array
    {
        return [
            'parents' => Service::query()
                ->whereNull('parent_id')
                ->when($service?->exists, fn ($query) => $query->whereKeyNot($service->getKey()))
                ->with('translations')
                ->ordered()
                ->get(),
            'technologies' => Technology::query()->with(['translations', 'category.translations'])->ordered()->get(),
            'images' => Media::query()
                ->where('status', MediaStatus::Ready)
                ->where('mime_type', 'like', 'image/%')
                ->latest('id')
                ->limit(200)
                ->get(['id', 'uuid', 'original_name', 'alt']),
        ];
    }
}
