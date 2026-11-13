<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Radar\GenerateRadarProposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateRadarProposalRequest;
use App\Http\Requests\Admin\UpdateRadarProposalRequest;
use App\Models\RadarProject;
use App\Models\RadarProposal;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

/**
 * Proposal drafts: generate with AI, then edit and copy. There is deliberately no "submit" endpoint.
 */
class RadarProposalController extends Controller
{
    public function store(GenerateRadarProposalRequest $request, RadarProject $project, GenerateRadarProposal $generate): RedirectResponse
    {
        $proposal = $generate->handle($project, $request->user(), $request->validated('locale'), $request->validated('facts'), $request->validated('price'));

        return redirect()->to(route('admin.radar.projects.show', $project).'#proposal-'.$proposal->id)->with('toast', __('Proposal draft ready. Review it, then copy it to the platform yourself.'));
    }

    public function update(UpdateRadarProposalRequest $request, RadarProject $project, RadarProposal $proposal, AuditLogger $audit): RedirectResponse
    {
        $proposal->forceFill(['body' => trim($request->validated('body')), 'updated_by' => $request->user()->getKey()])->save();

        $audit->record(operation: 'radar.proposal_edited', auditable: $project, user: $request->user(), after: ['proposal_id' => $proposal->id]);

        return redirect()->to(route('admin.radar.projects.show', $project).'#proposal-'.$proposal->id)->with('toast', __('Proposal draft saved.'));
    }
}
