<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tab  = request('tab','all');
        $notifications = $tab === 'unread'
            ? $user->unreadNotifications()->paginate(20)
            : $user->notifications()->paginate(20);

        $unreadCount = $user->unreadNotifications->count();
        return view('dashboard.notifications', compact('notifications','tab','unreadCount'));
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
