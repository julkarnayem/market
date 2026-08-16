<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;

class NotificationController extends Controller
{
    public function index()
    {
        $this->authorize('notifications.view');
        return view('admin.notifications');
    }

    public function smsLogs()
    {
        $this->authorize('sms.view');
        $status = request('status','all');
        $logs = SmsLog::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when(request('template'), fn($q) => $q->where('template', request('template')))
            ->with('user')->latest()->paginate(30);
        return view('admin.sms-logs', compact('logs','status'));
    }
}
