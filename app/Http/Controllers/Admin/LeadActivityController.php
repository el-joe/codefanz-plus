<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Leads\LogLeadActivity;
use App\Enums\LeadActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLeadActivityRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;

class LeadActivityController extends Controller
{
    public function store(StoreLeadActivityRequest $request, Lead $lead, LogLeadActivity $log): RedirectResponse
    {
        $log->handle($lead, LeadActivityType::from($request->validated('type')), $request->validated('body'), $request->user());

        return redirect()->route('admin.leads.show', $lead)->with('toast', __('Activity added.'));
    }
}
