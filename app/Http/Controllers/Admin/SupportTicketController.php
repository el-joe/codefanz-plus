<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Support\ReplyToTicket;
use App\Actions\Support\UpdateTicket;
use App\Enums\Permission;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplyToTicketRequest;
use App\Http\Requests\Admin\UpdateTicketRequest;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Support ticket queue (portal P12 pattern in the admin): filters, detail with the full thread (including
 * internal notes), public replies, internal notes, status / priority / assignee.
 */
class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SupportTicket::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([...array_column(TicketStatus::cases(), 'value'), 'open_all', 'all'])],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'assignee' => ['nullable', 'string', 'regex:/^(me|none|\d+)$/'],
        ]);
        $status = $filters['status'] ?? 'open_all';

        $tickets = SupportTicket::query()
            ->with(['customer:id,ulid,company_name', 'project:id,ulid,name', 'assignee:id,name'])
            ->when($status === 'open_all', fn (Builder $query) => $query->open())
            ->when(! in_array($status, ['open_all', 'all'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assignee'] ?? null, fn (Builder $query, string $assignee) => match ($assignee) {
                'me' => $query->where('assigned_to', $request->user()->getKey()),
                'none' => $query->whereNull('assigned_to'),
                default => $query->where('assigned_to', (int) $assignee),
            })
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('subject', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', $like)));
            })
            ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")
            ->latest('last_activity_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'status' => $status,
            'agents' => $this->agents(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        Gate::authorize('view', $ticket);

        $ticket->load(['customer:id,ulid,company_name,reference', 'project:id,ulid,name,reference', 'opener:id,name,email', 'assignee:id,name', 'replies.author:id,name']);

        return view('admin.tickets.show', ['ticket' => $ticket, 'agents' => $this->agents()]);
    }

    public function reply(ReplyToTicketRequest $request, SupportTicket $ticket, ReplyToTicket $reply): RedirectResponse
    {
        $internal = $request->boolean('internal');
        $reply->handle($ticket, $request->validated('body'), $request->user(), fromClient: false, internal: $internal);

        return redirect()->route('admin.tickets.show', $ticket)->with('toast', $internal ? __('Internal note added.') : __('Reply sent.'));
    }

    public function update(UpdateTicketRequest $request, SupportTicket $ticket, UpdateTicket $update): RedirectResponse
    {
        $update->handle(
            $ticket,
            TicketStatus::from($request->validated('status')),
            TicketPriority::from($request->validated('priority')),
            $request->validated('assigned_to') !== null ? (int) $request->validated('assigned_to') : null,
            $request->user(),
        );

        return redirect()->route('admin.tickets.show', $ticket)->with('toast', __('Ticket updated.'));
    }

    /**
     * @return Collection<int|string, string>
     */
    private function agents(): Collection
    {
        return User::query()->holdingPermission(Permission::SupportManage)->orderBy('name')->pluck('name', 'id');
    }
}
