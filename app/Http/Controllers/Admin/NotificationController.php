<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Services\Sms\SmsTemplates;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $this->authorize('notifications.view');

        return Inertia::render('Admin/Notifications/Index', [
            'stats' => [
                'total'  => SmsLog::count(),
                'sent'   => SmsLog::where('status', 'sent')->count(),
                'failed' => SmsLog::where('status', 'failed')->count(),
            ],
            'provider' => [
                'name'    => 'BulkSMSBD',
                'enabled' => (bool) (config('bulksmsbd.enabled') && config('bulksmsbd.api_key')),
            ],
        ]);
    }

    public function smsLogs()
    {
        $this->authorize('sms.view');
        $status   = request('status', 'all');
        $template = request('template');

        $logs = SmsLog::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($template, fn ($q) => $q->where('template', $template))
            ->with('user')->latest()->paginate(30)->withQueryString();

        return Inertia::render('Admin/Notifications/SmsLogs', [
            'logs' => $logs->through(fn (SmsLog $log) => [
                'id'       => $log->id,
                'user'     => $log->user?->name ?? '—',
                'phone'    => $log->maskedPhone(),
                'template' => $log->template,
                'status'   => $log->status,
                'attempts' => $log->attempts,
                'sent'     => $log->sent_at?->format('d M, H:i'),
                'error'    => $log->error_message,
            ]),
            'filters'   => ['status' => $status, 'template' => $template ?? ''],
            'statuses'  => [
                ['value' => 'sent',    'label' => 'Sent'],
                ['value' => 'failed',  'label' => 'Failed'],
                ['value' => 'pending', 'label' => 'Pending'],
            ],
            'templates' => array_keys(SmsTemplates::TEMPLATES),
        ]);
    }
}
