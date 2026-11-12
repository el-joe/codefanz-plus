<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FollowsNotificationLinks;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * Admin notification bell: list, open (marks as read and follows the link) and mark all as read.
 * Every lookup is scoped to the signed-in user's own notifications.
 */
class NotificationController extends Controller
{
    use FollowsNotificationLinks;

    public function index(Request $request): View
    {
        return view('admin.notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(25),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $record */
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        $lead = $record->data['lead'] ?? null;

        if (is_string($lead)) {
            return redirect()->route('admin.leads.show', $lead);
        }

        return redirect()->to($this->notificationPath($record->data['url'] ?? null, '/admin/') ?? route('admin.notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('toast', __('All notifications marked as read.'));
    }
}
