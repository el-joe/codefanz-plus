<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Leads\ChangeLeadStatus;
use App\Enums\LeadStatus;
use App\Exceptions\LeadVersionConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeLeadStatusRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Status changes from the detail page (form post) and the Kanban board (JSON via fetch).
 * The server re-validates the transition and the lock_version every time.
 */
class LeadStatusController extends Controller
{
    public function __invoke(ChangeLeadStatusRequest $request, Lead $lead, ChangeLeadStatus $change): JsonResponse|RedirectResponse
    {
        try {
            $lead = $change->handle(
                $lead,
                LeadStatus::from($request->validated('status')),
                $request->validated('lost_reason'),
                (int) $request->validated('lock_version'),
                $request->user(),
            );
        } catch (LeadVersionConflict $conflict) {
            $message = __('Someone else changed this lead. Reload to see the latest version.');

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'lock_version' => $conflict->currentVersion], 409)
                : back()->withErrors(['lock_version' => $message]);
        }

        $message = __('Moved :reference to :status.', ['reference' => $lead->reference, 'status' => $lead->status->label()]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'lead' => [
                    'id' => $lead->ulid,
                    'status' => $lead->status->value,
                    'status_label' => $lead->status->label(),
                    'lock_version' => $lead->lock_version,
                    'allowed' => array_map(fn (LeadStatus $status): string => $status->value, $lead->status->allowedTransitions()),
                ],
            ]);
        }

        return back()->with('toast', $message);
    }
}
