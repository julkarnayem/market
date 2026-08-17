<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tab  = request('tab', 'all');

        $notifications = ($tab === 'unread' ? $user->unreadNotifications() : $user->notifications())
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Dashboard/Notifications/Index', [
            'tab'         => $tab,
            'unreadCount' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->through(fn (DatabaseNotification $n) => [
                'id'            => $n->id,
                'icon'          => $this->iconFor($n->data['type'] ?? 'system'),
                'title'         => $n->data['title'] ?? 'Notification',
                'message'       => $n->data['message'] ?? '',
                'is_read'       => ! is_null($n->read_at),
                'created_human' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    /** Emoji for a notification type, matching the buckets the app dispatches. */
    private function iconFor(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'order')     => '📦',
            str_starts_with($type, 'payment')   => '💳',
            str_starts_with($type, 'listing')   => '🏷️',
            str_starts_with($type, 'offer')     => '🤝',
            str_starts_with($type, 'withdraw')  => '🏦',
            str_starts_with($type, 'dispute')   => '⚑',
            str_starts_with($type, 'wallet')    => '👛',
            str_starts_with($type, 'promotion') => '⭐',
            str_starts_with($type, 'verif')     => '✅',
            default                             => '🔔',
        };
    }

    public function markRead(string $id)
    {
        $n = Auth::user()->notifications()->findOrFail($id);
        $n->markAsRead();
        return back();
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(string $id)
    {
        Auth::user()->notifications()->findOrFail($id)->delete();
        return back();
    }
}
