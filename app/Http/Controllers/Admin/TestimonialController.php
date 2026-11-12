<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SaveTestimonial;
use App\Enums\ContentStatus;
use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTestimonialRequest;
use App\Models\Language;
use App\Models\Media;
use App\Models\PortfolioProject;
use App\Models\Testimonial;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Testimonial::class);

        return view('admin.testimonials.index', [
            'testimonials' => Testimonial::query()->with(['translations', 'project.translations'])->ordered()->paginate(30),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Testimonial::class);

        return view('admin.testimonials.form', [...$this->formOptions(), 'testimonial' => new Testimonial]);
    }

    public function store(SaveTestimonialRequest $request, SaveTestimonial $save): RedirectResponse
    {
        $testimonial = $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.testimonials.edit', $testimonial)->with('toast', __('Testimonial created as a draft.'));
    }

    public function edit(Testimonial $testimonial): View
    {
        Gate::authorize('update', $testimonial);

        return view('admin.testimonials.form', [...$this->formOptions(), 'testimonial' => $testimonial->load(['translations', 'avatar'])]);
    }

    public function update(SaveTestimonialRequest $request, Testimonial $testimonial, SaveTestimonial $save): RedirectResponse
    {
        $wasPublished = $testimonial->status === ContentStatus::Published;
        $testimonial = $save->handle($testimonial, $request->validated(), $request->user());

        $message = $wasPublished && $testimonial->status === ContentStatus::Draft
            ? __('Consent withdrawn: the testimonial was unpublished.')
            : __('Testimonial saved.');

        return redirect()->route('admin.testimonials.edit', $testimonial)->with('toast', $message);
    }

    public function publish(Request $request, Testimonial $testimonial, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('publish', $testimonial);

        $publish = $testimonial->status !== ContentStatus::Published;

        if ($publish && ! $testimonial->consent_confirmed) {
            return back()->withErrors(['status' => __('Confirm that the client agreed to publication before publishing.')]);
        }

        if ($publish && ! $testimonial->translations()->where('locale', Language::defaultCode())->exists()) {
            return back()->withErrors(['status' => __('Add the default-language content before publishing.')]);
        }

        $testimonial->forceFill([
            'status' => $publish ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $publish ? ($testimonial->published_at ?? now()) : $testimonial->published_at,
        ])->save();

        $audit->record(operation: $publish ? 'testimonial.published' : 'testimonial.unpublished', auditable: $testimonial, user: $request->user());

        return back()->with('toast', $publish ? __('Testimonial published.') : __('Testimonial moved back to draft.'));
    }

    public function destroy(Request $request, Testimonial $testimonial, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $testimonial);

        $testimonial->delete();
        $audit->record(operation: 'testimonial.deleted', auditable: $testimonial, user: $request->user());

        return redirect()->route('admin.testimonials.index')->with('toast', __('Testimonial moved to trash.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'projects' => PortfolioProject::query()->with('translations')->ordered()->get(),
            'images' => Media::query()
                ->where('status', MediaStatus::Ready)
                ->where('mime_type', 'like', 'image/%')
                ->latest('id')
                ->limit(200)
                ->get(['id', 'uuid', 'original_name', 'alt']),
        ];
    }
}
