<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Concerns\FollowsNotificationLinks;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * Portal notifications (P13). Every lookup is scoped to the signed-in user's own notifications.
 */
class NotificationController extends Controller
{
    use FollowsNotificationLinks;

    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $query = $filter === 'unread' ? $request->user()->unreadNotifications() : $request->user()->notifications();

        return view('portal.notifications', [
            'notifications' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $record */
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return redirect()->to($this->notificationPath($record->data['url'] ?? null, '/portal/') ?? route('portal.notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('toast', __('All notifications marked as read.'));
    }
}
