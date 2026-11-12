<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Leads\AssignLead;
use App\Exceptions\LeadVersionConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignLeadRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class LeadAssignmentController extends Controller
{
    public function __invoke(AssignLeadRequest $request, Lead $lead, AssignLead $assign): RedirectResponse
    {
        $assigneeId = $request->validated('assigned_to');

        try {
            $assign->handle(
                $lead,
                $assigneeId ? User::query()->findOrFail($assigneeId) : null,
                (int) $request->validated('lock_version'),
                $request->user(),
            );
        } catch (LeadVersionConflict) {
            return back()->withErrors(['lock_version' => __('Someone else changed this lead. Reload to see the latest version.')]);
        }

        return back()->with('toast', $assigneeId ? __('Lead assigned.') : __('Lead unassigned.'));
    }
}
