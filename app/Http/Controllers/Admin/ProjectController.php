<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\CreatePaymentLink;
use App\Actions\Projects\SaveProject;
use App\Enums\ClientVisibility;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProjectRequest;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Milestone;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Money;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Delivery projects (A04): list, create from a customer or a won lead's customer, detail with tabs
 * (overview, milestones, payments, documents, conversation, tickets), edit and soft delete. Staff who may create
 * payment links get the Create payment link dialog (A04) with each milestone's remaining balance.
 */
class ProjectController extends Controller
{
    /**
     * @var list<string>
     */
    public const TABS = ['overview', 'milestones', 'payments', 'documents', 'conversation', 'tickets'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'manager' => ['nullable', 'string', 'regex:/^(me|\d+)$/'],
        ]);

        $projects = Project::query()
            ->with(['customer:id,ulid,company_name', 'manager:id,name', 'milestones:id,project_id,status'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['manager'] ?? null, fn (Builder $query, string $manager) => $query->where('project_manager_id', $manager === 'me' ? $request->user()->getKey() : (int) $manager))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.projects.index', ['projects' => $projects, 'filters' => $filters]);
    }

    /**
     * New project form: `?customer=<ulid>` from a customer page, `?lead=<ulid>` from a won lead that was converted.
     */
    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $project = new Project(['currency' => config('codefanz.projects.default_currency'), 'start_date' => now()]);
        $customer = null;
        $lead = null;

        if ($request->filled('lead')) {
            $lead = Lead::query()->with('customer')->where('ulid', (string) $request->query('lead'))->firstOrFail();
            Gate::authorize('view', $lead);

            if ($lead->status !== LeadStatus::Won || $lead->customer === null) {
                return redirect()->route('admin.leads.show', $lead)->with('toast', __('Convert the won lead into a customer before starting a project.'));
            }

            $customer = $lead->customer;
            $project->fill([
                'name' => $lead->company ? $lead->company : $lead->name,
                'description' => $lead->description,
                'budget_minor' => $lead->estimated_value_minor,
                'currency' => $lead->currency ?? $project->currency,
            ]);
        } elseif ($request->filled('customer')) {
            $customer = Customer::query()->where('ulid', (string) $request->query('customer'))->firstOrFail();
        }

        return view('admin.projects.form', [
            'project' => $project,
            'customer' => $customer,
            'lead' => $lead,
            'customers' => $customer ? null : Customer::query()->orderBy('company_name')->pluck('company_name', 'ulid'),
            'staff' => $this->staff(),
        ]);
    }

    public function store(SaveProjectRequest $request, SaveProject $save): RedirectResponse
    {
        $project = $save->handle(null, $request->projectData(), $request->members(), $request->user());

        return redirect()->route('admin.projects.show', $project)->with('toast', __('Project :reference created.', ['reference' => $project->reference]));
    }

    public function show(Request $request, Project $project, PaymentGatewayManager $gateways): View
    {
        Gate::authorize('view', $project);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';

        $project->load([
            'customer:id,ulid,company_name,reference',
            'lead:id,ulid,reference,name',
            'manager:id,name',
            'members:id,name,email',
            'milestones' => fn ($query) => $query->with('dependencies:id,title,status'),
        ]);

        $data = ['project' => $project, 'tab' => $tab];

        if ($tab === 'overview') {
            $data['notes'] = $project->notes()->with('author:id,name')->latest()->latest('id')->limit(50)->get();
        }

        if ($tab === 'documents') {
            $data['documents'] = $project->documents()->with('uploader:id,name')->orderBy('title')->orderByDesc('version')->get()->groupBy('title');
        }

        if ($tab === 'conversation') {
            $conversations = $project->conversations()->withUnreadCountFor($request->user())->orderByDesc('last_message_at')->orderBy('id')->get();
            $active = $conversations->firstWhere('ulid', (string) $request->query('conversation')) ?? $conversations->first();

            if ($active !== null) {
                $active->load(['messages' => fn ($query) => $query->with(['author:id,name', 'attachments'])]);
                $active->markReadBy($request->user());
            }

            $data['conversations'] = $conversations;
            $data['activeConversation'] = $active;
        }

        if ($tab === 'tickets') {
            $data['tickets'] = $request->user()->can('viewAny', SupportTicket::class)
                ? $project->tickets()->with('assignee:id,name')->latest('last_activity_at')->get()
                : new Collection;
        }

        if ($tab === 'payments') {
            $canSeePayments = $request->user()->can('viewAny', Payment::class);
            $data['paymentLinks'] = $canSeePayments ? $project->paymentLinks()->with(['milestone:id,title', 'creator:id,name'])->latest()->latest('id')->get() : new Collection;
            $data['payments'] = $canSeePayments ? $project->payments()->with(['milestone:id,title'])->whereNotNull('gateway_payment_id')->latest()->latest('id')->get() : new Collection;
            $data['invoices'] = $canSeePayments ? $project->invoices()->with('milestone:id,title')->latest()->latest('id')->get() : new Collection;
        }

        if ($request->user()->can('create', PaymentLink::class)) {
            $data['linkDialog'] = [
                'remaining' => $project->milestones->mapWithKeys(fn (Milestone $milestone): array => [$milestone->ulid => CreatePaymentLink::remainingBalance($milestone)])->all(),
                'gateways' => $gateways->statuses(),
            ];
        }

        $data['clientUpdatesCount'] = $project->notes()->where('visibility', ClientVisibility::Client)->count();
        $data['milestonesTotal'] = Money::format($project->milestonesTotalMinor(), $project->currency);

        return view('admin.projects.show', $data);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        $project->load(['customer:id,ulid,company_name', 'members:id']);

        return view('admin.projects.form', [
            'project' => $project,
            'customer' => $project->customer,
            'lead' => null,
            'customers' => null,
            'staff' => $this->staff(),
        ]);
    }

    public function update(SaveProjectRequest $request, Project $project, SaveProject $save): RedirectResponse
    {
        $save->handle($project, $request->projectData(), $request->members(), $request->user(), (int) $request->validated('lock_version'));

        return redirect()->route('admin.projects.show', $project)->with('toast', __('Project saved.'));
    }

    public function destroy(Request $request, Project $project, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();
        $audit->record(operation: 'project.deleted', auditable: $project, user: $request->user(), before: ['reference' => $project->reference, 'name' => $project->name]);

        return redirect()->route('admin.projects.index')->with('toast', __('Project deleted.'));
    }

    /**
     * @return Collection<int, User>
     */
    private function staff(): Collection
    {
        return User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', '!=', 'client'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
