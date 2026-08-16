<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReport;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('disputes.manage'); // reuse moderation permission
        $status   = request('status','pending');
        $reports  = MessageReport::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->with(['message.sender','message.conversation.order','reporter'])
            ->latest()->paginate(25);
        return view('admin.message-reports', compact('reports','status'));
    }

    public function review(Request $request, MessageReport $report)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate([
            'action' => 'required|in:dismiss,warn,delete_message',
            'note'   => 'nullable|string|max:500',
        ]);

        $report->update([
            'status'      => $data['action'] === 'dismiss' ? 'dismissed' : 'actioned',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        if ($data['action'] === 'delete_message' && $report->message) {
            $report->message->delete();
            $this->audit->log('message.moderated', $report->message, [], ['action' => 'deleted'], $data['note'] ?? '', 'moderation');
        }

        $this->audit->log('message_report.reviewed', $report, [], ['action' => $data['action']], '', 'moderation');
        return back()->with('success', 'Report reviewed.');
    }
}
