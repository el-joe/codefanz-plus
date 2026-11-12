<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Content\SavePageSection;
use App\Actions\Content\TranslatedList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePageSectionRequest;
use App\Models\PageSection;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Page section manager (A08): reorder, show/hide and edit each section's copy per language, for every
 * section-managed page (homepage, About, Careers).
 */
class HomeSectionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PageSection::class);

        $page = $request->query('page');
        $page = is_string($page) && array_key_exists($page, PageSection::PAGES) ? $page : PageSection::HOME;

        PageSection::ensureSections($page);

        return view('admin.home-sections.index', [
            'page' => $page,
            'sections' => PageSection::query()->where('page', $page)->with('translations')->ordered()->get(),
        ]);
    }

    public function edit(PageSection $section): View
    {
        Gate::authorize('update', $section);

        $section->load('translations');

        return view('admin.home-sections.form', [
            'section' => $section,
            'items' => TranslatedList::toRows($section->translations, 'items', PageSection::ITEM_FIELDS),
        ]);
    }

    public function update(SavePageSectionRequest $request, PageSection $section, SavePageSection $save): RedirectResponse
    {
        $save->handle($section, $request->validated(), $request->user());

        return redirect()->route('admin.home-sections.edit', $section)->with('toast', __('Section saved.'));
    }

    public function visibility(Request $request, PageSection $section, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $section);

        $section->forceFill(['is_visible' => ! $section->is_visible, 'updated_by' => $request->user()->getKey()])->save();
        $audit->record(operation: $section->is_visible ? 'page_section.shown' : 'page_section.hidden', auditable: $section, user: $request->user());

        return back()->with('toast', $section->is_visible ? __('Section shown on the page.') : __('Section hidden from the page.'));
    }

    /**
     * Swap the section with its neighbour (works without JavaScript).
     */
    public function move(Request $request, PageSection $section, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $section);

        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($section, $direction): void {
            $sections = PageSection::query()->where('page', $section->page)->ordered()->lockForUpdate()->get()->values();
            $index = $sections->search(fn (PageSection $candidate): bool => $candidate->is($section));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $sections->has($target)) {
                return;
            }

            $ordered = $sections->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

            foreach (array_values($ordered) as $position => $candidate) {
                $candidate->forceFill(['sort_order' => $position])->save();
            }
        });

        $audit->record(operation: 'page_section.moved', auditable: $section, user: $request->user(), after: ['direction' => $direction]);

        return back()->with('toast', __('Section order updated.'));
    }
}
