<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index()
    {
        $this->authorize('tickets.manage');
        $status = request('status','open');
        $tickets = SupportTicket::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when(request('q'), fn($q) => $q->where('subject','like','%'.request('q').'%')
                ->orWhere('reference','like','%'.request('q').'%'))
            ->when(request('priority'), fn($q) => $q->where('priority', request('priority')))
            ->with('user','assignee')
            ->latest('last_reply_at')->paginate(25);
        $staffList = User::whereHas('roles', fn($q) => $q->where('is_admin_role', true))->get(['id','name']);
        return view('admin.tickets.index', compact('tickets','status','staffList'));
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorize('tickets.manage');
        $ticket->load(['messages.user','user','assignee']);
        $staffList = User::whereHas('roles', fn($q) => $q->where('is_admin_role', true))->get(['id','name']);
        return view('admin.tickets.show', compact('ticket','staffList'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate([
            'body'       => 'required|string|max:5000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,txt',
        ]);
        $this->service->reply($ticket, Auth::user(), $data['body'], $request->file('attachment'), true);
        return back()->with('success', 'Reply sent to user.');
    }

    public function assign(Request $request, SupportTicket $ticket)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate(['assigned_to' => 'nullable|exists:users,id']);
        $staff = $data['assigned_to'] ? User::find($data['assigned_to']) : null;
        $this->service->assign($ticket, $staff, Auth::user());
        return back()->with('success', $staff ? "Assigned to {$staff->name}." : 'Unassigned.');
    }

    public function status(Request $request, SupportTicket $ticket)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate([
            'status' => 'required|in:open,in_progress,waiting_for_user,resolved,closed',
            'reason' => 'nullable|string|max:500',
        ]);
        $this->service->changeStatus($ticket, $data['status'], Auth::user(), $data['reason'] ?? '');
        return back()->with('success', 'Ticket status updated.');
    }

    public function internalNote(Request $request, SupportTicket $ticket)
    {
        $this->authorize('tickets.manage');
        $data = $request->validate(['body' => 'required|string|max:3000']);
        // is_internal_note=true — never shown to user
        $ticket->messages()->create([
            'user_id'          => Auth::id(),
            'body'             => $data['body'],
            'is_staff_reply'   => true,
            'is_internal_note' => true,
        ]);
        $ticket->touch();
        return back()->with('success', 'Internal note added.');
    }

    public function priority(Request $request, SupportTicket $ticket)
    {
        $this->authorize('tickets.manage');
        $request->validate(['priority' => 'required|in:low,normal,high,urgent']);
        $ticket->update(['priority' => $request->priority]);
        return back()->with('success', 'Priority updated.');
    }
}
