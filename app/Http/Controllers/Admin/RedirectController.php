<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Seo\ImportRedirects;
use App\Actions\Seo\SaveRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportRedirectsRequest;
use App\Http\Requests\Admin\SaveRedirectRequest;
use App\Models\Redirect;
use App\Services\AuditLogger;
use App\Services\Seo\RedirectPaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Managed redirects (seo.manage): list with hits, create/edit with loop and chain checks, CSV import, delete.
 */
class RedirectController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);

        return view('admin.seo.redirects.index', [
            'redirects' => Redirect::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('from_path', 'like', '%'.addcslashes($search, '\\%_').'%')->orWhere('to_path', 'like', '%'.addcslashes($search, '\\%_').'%')))
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $from = RedirectPaths::source((string) $request->query('from', '')) ?? '';

        return view('admin.seo.redirects.form', ['redirect' => new Redirect(['from_path' => $from === '/' ? '' : $from])]);
    }

    public function store(SaveRedirectRequest $request, SaveRedirect $save): RedirectResponse
    {
        $save->handle(null, $request->validated(), $request->user());

        return redirect()->route('admin.redirects.index')->with('toast', __('Redirect saved.'));
    }

    public function edit(Redirect $redirect): View
    {
        return view('admin.seo.redirects.form', ['redirect' => $redirect]);
    }

    public function update(SaveRedirectRequest $request, Redirect $redirect, SaveRedirect $save): RedirectResponse
    {
        $save->handle($redirect, $request->validated(), $request->user());

        return redirect()->route('admin.redirects.index')->with('toast', __('Redirect saved.'));
    }

    public function destroy(Request $request, Redirect $redirect, AuditLogger $audit): RedirectResponse
    {
        $audit->record(operation: 'redirect.deleted', auditable: $redirect, user: $request->user(), before: $redirect->only(['from_path', 'to_path', 'status_code']));
        $redirect->delete();

        return redirect()->route('admin.redirects.index')->with('toast', __('Redirect deleted.'));
    }

    public function import(ImportRedirectsRequest $request, ImportRedirects $import): RedirectResponse
    {
        $result = $import->handle($request->file('file'), $request->user());

        return redirect()->route('admin.redirects.index')
            ->with('toast', trans_choice('{0} No redirects imported.|{1} 1 redirect imported.|[2,*] :count redirects imported.', $result['imported'], ['count' => $result['imported']]))
            ->with('importErrors', $result['errors']);
    }
}
