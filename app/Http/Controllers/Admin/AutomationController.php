<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Automation\SaveAutomationWorkflow;
use App\Enums\AutomationAction;
use App\Enums\AutomationDirection;
use App\Enums\AutomationEvent;
use App\Enums\AutomationRunStatus;
use App\Enums\KillSwitchKey;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAutomationWorkflowRequest;
use App\Jobs\DeliverAutomationWebhook;
use App\Models\AutomationRun;
use App\Models\AutomationWorkflow;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Automation\AutomationDispatcher;
use App\Services\KillSwitches;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * B10 n8n integrations (automation.manage): outbound workflows and inbound endpoints, enable / disable, secret
 * shown once (rotate to replace), "send test event", the run log with filters and a retry for failed deliveries.
 */
class AutomationController extends Controller
{
    public function index(Request $request, KillSwitches $killSwitches): View
    {
        $filters = $request->validate([
            'workflow' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(AutomationRunStatus::class)],
        ]);

        $workflows = AutomationWorkflow::query()->with('actingUser')->orderBy('direction')->orderBy('name')->get();

        return view('admin.automation.index', [
            'workflows' => $workflows,
            'runs' => AutomationRun::query()
                ->with('workflow')
                ->when($filters['workflow'] ?? null, fn ($query, $id) => $query->where('automation_workflow_id', $id))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'paused' => $killSwitches->isActive(KillSwitchKey::AutomationWebhooks),
        ]);
    }

    public function create(Request $request): View
    {
        $direction = AutomationDirection::tryFrom((string) $request->query('direction')) ?? AutomationDirection::Outbound;

        return view('admin.automation.form', ['workflow' => new AutomationWorkflow(['direction' => $direction, 'is_enabled' => true, 'events' => []]), ...$this->formData()]);
    }

    public function store(SaveAutomationWorkflowRequest $request, SaveAutomationWorkflow $save): RedirectResponse
    {
        [$workflow, $secret] = $save->handle(null, $request->workflowData(), $request->user());

        return to_route('admin.automation.edit', $workflow)
            ->with('automation_secret', $secret)
            ->with('toast', __('Workflow created. Copy the signing secret now — it will not be shown again.'));
    }

    public function edit(AutomationWorkflow $workflow): View
    {
        $workflow->load('actingUser');

        return view('admin.automation.form', [
            'workflow' => $workflow,
            'recentRuns' => $workflow->runs()->latest('id')->limit(10)->get(),
            ...$this->formData(),
        ]);
    }

    public function update(SaveAutomationWorkflowRequest $request, AutomationWorkflow $workflow, SaveAutomationWorkflow $save): RedirectResponse
    {
        $save->handle($workflow, $request->workflowData(), $request->user());

        return to_route('admin.automation.edit', $workflow)->with('toast', __('Workflow saved.'));
    }

    public function destroy(Request $request, AutomationWorkflow $workflow, SaveAutomationWorkflow $save): RedirectResponse
    {
        $save->delete($workflow, $request->user());

        return to_route('admin.automation.index')->with('toast', __('Workflow deleted.'));
    }

    public function toggle(Request $request, AutomationWorkflow $workflow, SaveAutomationWorkflow $save): RedirectResponse
    {
        $save->toggle($workflow, ! $workflow->is_enabled, $request->user());

        return back()->with('toast', $workflow->is_enabled ? __('Workflow enabled.') : __('Workflow disabled.'));
    }

    public function rotate(Request $request, AutomationWorkflow $workflow, SaveAutomationWorkflow $save): RedirectResponse
    {
        $secret = $save->rotateSecret($workflow, $request->user());

        return to_route('admin.automation.edit', $workflow)
            ->with('automation_secret', $secret)
            ->with('toast', __('New signing secret issued. Update it in n8n — the old one no longer works.'));
    }

    public function test(Request $request, AutomationWorkflow $workflow, AutomationDispatcher $dispatcher, AuditLogger $audit): RedirectResponse
    {
        abort_unless($workflow->isOutbound(), 404);

        $run = $dispatcher->queue($workflow, AutomationEvent::Test, ['message' => 'Test event from CodeFanz', 'workflow' => $workflow->key, 'sent_by' => $request->user()->name]);

        $audit->record(operation: 'automation.test_sent', auditable: $workflow, user: $request->user(), after: ['run' => $run->uuid]);

        return back()->with('toast', __('Test event queued. Its result appears in the run log.'));
    }

    public function retry(Request $request, AutomationRun $run, AuditLogger $audit): RedirectResponse
    {
        abort_unless($run->direction === AutomationDirection::Outbound && $run->status === AutomationRunStatus::Failed, 404);

        $run->forceFill(['status' => AutomationRunStatus::Pending, 'attempts' => 0, 'error' => null, 'completed_at' => null])->save();
        DeliverAutomationWebhook::dispatch($run->id);

        $audit->record(operation: 'automation.run_retried', auditable: $run->workflow, user: $request->user(), after: ['run' => $run->uuid]);

        return back()->with('toast', __('Delivery queued again.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'directions' => AutomationDirection::options(),
            'events' => AutomationEvent::subscribable(),
            'actions' => AutomationAction::options(),
            'staff' => User::query()->holdingPermission(Permission::AdminAccess)->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
