<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageReport;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MessageReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index()
    {
        $this->authorize('disputes.manage'); // reuse moderation permission
        $status  = request('status', 'pending');
        $reports = MessageReport::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['message.sender', 'message.conversation.order', 'reporter'])
            ->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/MessageReports/Index', [
            'reports' => $reports->through(fn (MessageReport $r) => [
                'id'           => $r->id,
                'reporter'     => $r->reporter?->name ?? '—',
                'message'      => $r->message ? Str::limit((string) $r->message->body, 60) : '—',
                'sender'       => $r->message?->sender?->name ?? '—',
                'reason'       => ucfirst((string) $r->reason),
                'order_number' => $r->message?->conversation?->order?->order_number,
                'order_url'    => $r->message?->conversation?->order
                    ? route('admin.orders.show', $r->message->conversation->order)
                    : null,
                'status'       => $r->status,
                'date'         => $r->created_at->format('d M Y'),
            ]),
            'filters' => ['status' => $status],
            'tabs'    => $this->statusTabs(),
        ]);
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

    /** @return list<array{value:string,label:string}> */
    private function statusTabs(): array
    {
        return [
            ['value' => 'pending',   'label' => 'Pending'],
            ['value' => 'reviewed',  'label' => 'Reviewed'],
            ['value' => 'dismissed', 'label' => 'Dismissed'],
            ['value' => 'actioned',  'label' => 'Actioned'],
            ['value' => 'all',       'label' => 'All'],
        ];
    }
}
