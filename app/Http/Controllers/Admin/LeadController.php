<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Leads\CreateLead;
use App\Actions\Leads\UpdateLead;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Exceptions\LeadVersionConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLeadRequest;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Technology;
use App\Models\User;
use App\Services\Countries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Leads table (A03), Kanban board (A02), detail page with timeline, manual creation and edits.
 */
class LeadController extends Controller
{
    /**
     * Columns the table may be sorted by.
     *
     * @var list<string>
     */
    private const SORTABLE = ['created_at', 'updated_at', 'follow_up_at', 'name', 'estimated_value_minor'];

    /**
     * Leads per Kanban column.
     */
    private const BOARD_LIMIT = 50;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'assignee' => ['nullable', 'string', 'regex:/^(me|none|\d+)$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'follow_up' => ['nullable', Rule::in(['due'])],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $leads = $this->filtered(Lead::query(), $filters, $request->user())
            ->with('assignee:id,name')
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(25)
            ->withQueryString();

        return view('admin.leads.index', [
            'leads' => $leads,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'assignees' => $this->assignees(),
        ]);
    }

    public function board(Request $request): View
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'assignee' => ['nullable', 'string', 'regex:/^(me|none|\d+)$/'],
        ]);

        $columns = collect(LeadStatus::cases())->mapWithKeys(function (LeadStatus $status) use ($filters, $request): array {
            $query = $this->filtered(Lead::query()->where('status', $status), $filters, $request->user())
                // Closed deals only for the last 30 days; older ones stay in the table.
                ->when(! $status->isOpen(), fn (Builder $query) => $query->where('updated_at', '>=', now()->subDays(30)));

            return [$status->value => [
                'status' => $status,
                'total' => (clone $query)->count(),
                'leads' => $query->with('assignee:id,name')->latest('updated_at')->latest('id')->limit(self::BOARD_LIMIT)->get(),
            ]];
        });

        return view('admin.leads.board', [
            'columns' => $columns,
            'filters' => $filters,
            'assignees' => $this->assignees(),
        ]);
    }

    public function create(Countries $countries): View
    {
        Gate::authorize('create', Lead::class);

        return view('admin.leads.form', [
            'lead' => new Lead(['preferred_locale' => app()->getLocale()]),
            ...$this->formOptions($countries),
        ]);
    }

    public function store(SaveLeadRequest $request, CreateLead $createLead): RedirectResponse
    {
        $lead = $createLead->handle($request->leadData(), LeadSource::Manual, creator: $request->user());

        return redirect()->route('admin.leads.show', $lead)->with('toast', __('Lead :reference created.', ['reference' => $lead->reference]));
    }

    public function show(Lead $lead, Countries $countries): View
    {
        Gate::authorize('view', $lead);

        $lead->load([
            'assignee:id,name',
            'creator:id,name',
            'services.translations',
            'technologies.translations',
            'attachments',
            'contactMessage',
            'possibleDuplicateOf:id,ulid,reference,name,status',
            'customer:id,ulid,reference,created_from_lead_id',
            'chatSession.messages',
            'activities' => fn ($query) => $query->with('user:id,name')->latest()->latest('id'),
        ]);

        return view('admin.leads.show', [
            'lead' => $lead,
            'countryName' => $lead->country ? $countries->name($lead->country) : null,
            'assignees' => $this->assignees(),
        ]);
    }

    public function edit(Lead $lead, Countries $countries): View
    {
        Gate::authorize('update', $lead);

        $lead->load(['services:id', 'technologies:id']);

        return view('admin.leads.form', ['lead' => $lead, ...$this->formOptions($countries)]);
    }

    public function update(SaveLeadRequest $request, Lead $lead, UpdateLead $updateLead): RedirectResponse
    {
        try {
            $updateLead->handle($lead, $request->leadData(), (int) $request->validated('lock_version'), $request->user());
        } catch (LeadVersionConflict) {
            return back()->withInput()->withErrors(['lock_version' => __('Someone else changed this lead while you were editing. Reload to see their changes.')]);
        }

        return redirect()->route('admin.leads.show', $lead)->with('toast', __('Lead saved.'));
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Lead>
     */
    private function filtered(Builder $query, array $filters, User $user): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('company', 'like', $like)
                    ->orWhere('reference', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['assignee'] ?? null, fn (Builder $query, string $assignee) => match ($assignee) {
                'me' => $query->where('assigned_to', $user->getKey()),
                'none' => $query->whereNull('assigned_to'),
                default => $query->where('assigned_to', (int) $assignee),
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->when(($filters['follow_up'] ?? null) === 'due', fn (Builder $query) => $query->open()->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay()));
    }

    /**
     * Staff who may own leads (id => name).
     *
     * @return Collection<int, string>
     */
    private function assignees(): Collection
    {
        return User::query()->leadManagers()->orderBy('name')->pluck('name', 'id');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Countries $countries): array
    {
        return [
            'countries' => $countries->options(),
            'services' => Service::query()->with('translations')->ordered()->get(),
            'technologies' => Technology::query()->with('translations')->ordered()->get(),
            'assignees' => $this->assignees(),
        ];
    }
}
