<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Projects\AddProjectNote;
use App\Enums\ClientVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectNoteRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectNoteController extends Controller
{
    public function store(StoreProjectNoteRequest $request, Project $project, AddProjectNote $add): RedirectResponse
    {
        $visibility = ClientVisibility::from($request->validated('visibility'));
        $add->handle($project, $visibility, $request->validated('body'), $request->user());

        return redirect()->route('admin.projects.show', $project)
            ->with('toast', $visibility === ClientVisibility::Client ? __('Update shared with the client.') : __('Internal note added.'));
    }
}
