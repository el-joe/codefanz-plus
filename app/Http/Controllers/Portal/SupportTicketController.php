<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Support\OpenSupportTicket;
use App\Actions\Support\ReplyToTicket;
use App\Actions\Support\UpdateTicket;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\OpenTicketRequest;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Services\CurrentCustomer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Support tickets (P12): status tabs, open a ticket, thread with public replies only, reply and mark as resolved.
 * Tickets are resolved inside the current customer, so another customer's ticket is a 404.
 */
class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $tab = in_array($request->query('status'), ['open', 'waiting', 'resolved', 'all'], true) ? $request->query('status') : 'open';

        $base = fn (): Builder => SupportTicket::query()->forCurrentCustomer();
        $scopes = [
            'open' => fn (Builder $query) => $query->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingSupport]),
            'waiting' => fn (Builder $query) => $query->where('status', TicketStatus::AwaitingCustomer),
            'resolved' => fn (Builder $query) => $query->whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed]),
            'all' => fn (Builder $query) => $query,
        ];

        return view('portal.support.index', [
            'tickets' => $scopes[$tab]($base())->with('project:id,name')->latest('last_activity_at')->latest('id')->paginate(20)->withQueryString(),
            'tab' => $tab,
            'counts' => collect($scopes)->map(fn ($scope) => $scope($base())->count())->all(),
        ]);
    }

    public function create(): View
    {
        return view('portal.support.create', [
            'projects' => Project::query()->forCurrentCustomer()->orderBy('name')->pluck('name', 'ulid'),
        ]);
    }

    public function store(OpenTicketRequest $request, CurrentCustomer $currentCustomer, OpenSupportTicket $open): RedirectResponse
    {
        $ticket = $open->handle($currentCustomer->get(), $request->ticketData(), $request->user());

        return redirect()->route('portal.support.show', $ticket)->with('toast', __('Ticket :reference opened. We will reply soon.', ['reference' => $ticket->reference]));
    }

    public function show(string $ticket): View
    {
        $ticket = $this->find($ticket);
        $ticket->load(['project:id,ulid,name', 'assignee:id,name', 'publicReplies.author:id,name']);

        return view('portal.support.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, string $ticket, ReplyToTicket $reply): RedirectResponse
    {
        $ticket = $this->find($ticket);
        $validated = $request->validate(['body' => ['required', 'string', 'max:'.config('codefanz.support.reply_max_length')]], [], ['body' => __('Reply')]);

        $reply->handle($ticket, $validated['body'], $request->user(), fromClient: true);

        return redirect()->route('portal.support.show', $ticket)->with('toast', __('Reply sent.'));
    }

    public function resolve(Request $request, string $ticket, UpdateTicket $update): RedirectResponse
    {
        $ticket = $this->find($ticket);
        $update->handle($ticket, TicketStatus::Resolved, null, null, $request->user(), byClient: true);

        return redirect()->route('portal.support.show', $ticket)->with('toast', __('Ticket marked as resolved.'));
    }

    private function find(string $ulid): SupportTicket
    {
        return SupportTicket::query()->forCurrentCustomer()->where('ulid', $ulid)->firstOrFail();
    }
}
