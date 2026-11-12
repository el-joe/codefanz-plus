<?php

namespace App\Http\Controllers\Portal;

use App\Enums\MilestoneStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Invoice;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\SupportTicket;
use App\Services\CurrentCustomer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal home (P04): real counts for the current customer (active projects, open tickets, unread messages),
 * the most recent active project, upcoming milestones, recent invoices (with Pay now) and the latest conversations.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentCustomer $currentCustomer): View
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forCurrentCustomer()
            ->whereHas('project')
            ->with(['project:id,ulid,name', 'latestMessage.author:id,name'])
            ->withUnreadCountFor($user)
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->get();

        $activeProjectIds = Project::query()->forCurrentCustomer()->active()->pluck('id');

        return view('portal.dashboard', [
            'customer' => $currentCustomer->get(),
            'recentInvoices' => Invoice::query()->forCurrentCustomer()->visibleToClient()
                ->with('paymentLink:id,ulid,status,expires_at')
                ->latest('issued_at')->latest('id')->limit(3)->get(),
            'unreadNotificationCount' => $user->unreadNotifications()->count(),
            'activeProjectCount' => $activeProjectIds->count(),
            'openTicketCount' => SupportTicket::query()->forCurrentCustomer()->open()->count(),
            'unreadMessageCount' => (int) $conversations->sum('unread_count'),
            'activeProject' => Project::query()->forCurrentCustomer()->active()->with('milestones:id,project_id,title,status,due_date,sort_order')->latest('updated_at')->first(),
            'upcomingMilestones' => Milestone::query()
                ->whereIn('project_id', $activeProjectIds)
                ->whereIn('status', [MilestoneStatus::Pending, MilestoneStatus::InProgress, MilestoneStatus::AwaitingApproval])
                ->with('project:id,ulid,name')
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->limit(5)
                ->get(),
            'recentConversations' => $conversations->take(4),
        ]);
    }
}
