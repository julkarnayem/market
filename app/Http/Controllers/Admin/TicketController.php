<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index()
    {
        // Reading the queue is `tickets.view` — the nav item (both sidebars) is
        // gated on that permission, and a moderator holds view without manage,
        // so authorizing manage here made their own menu link 403.
        $this->authorize('tickets.view');
        $status   = request('status', 'open');
        $q        = request('q');
        $priority = request('priority');

        $tickets = SupportTicket::query()
            ->when($status !== 'all', fn ($qq) => $qq->where('status', $status))
            // Grouped: an ungrouped orWhere would leak tickets of every status
            // and priority past the filters above.
            ->when($q, fn ($qq) => $qq->where(fn ($w) => $w
                ->where('subject', 'like', '%'.$q.'%')
                ->orWhere('reference', 'like', '%'.$q.'%')))
            ->when($priority, fn ($qq) => $qq->where('priority', $priority))
            ->with(['user', 'assignee'])
            ->latest('last_reply_at')->paginate(25)->withQueryString();

        return Inertia::render('Admin/Tickets/Index', [
            'tickets' => $tickets->through(fn (SupportTicket $t) => [
                'id'             => $t->id,
                'reference'      => $t->reference,
                'user_name'      => $t->user?->name ?? '—',
                'subject'        => $t->subject,
                'priority_label' => ucfirst((string) $t->priority),
                'priority_color' => $t->priorityColor(),
                'status'         => $t->status->value,
                'assignee'       => $t->assignee?->name,
                'last_reply'     => $t->last_reply_at?->diffForHumans() ?? '—',
                'url'            => route('admin.tickets.show', $t),
            ]),
            'filters'    => [
                'status'   => $status,
                'q'        => $q,
                'priority' => $priority,
            ],
            'tabs'       => $this->statusTabs(),
            'priorities' => $this->priorityOptions(),
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $this->authorize('tickets.view');
        $ticket->load(['messages.user', 'user', 'assignee']);

        return Inertia::render('Admin/Tickets/Show', [
            'ticket' => [
                'id'             => $ticket->id,
                'reference'      => $ticket->reference,
                'subject'        => $ticket->subject,
                'category'       => $ticket->category,
                'status'         => $ticket->status->value,
                'priority'       => $ticket->priority,
                'priority_label' => ucfirst((string) $ticket->priority),
                'priority_color' => $ticket->priorityColor(),
                'assigned_to'    => $ticket->assigned_to,
                'assignee_name'  => $ticket->assignee?->name,
                'user_name'      => $ticket->user?->name ?? '—',
                'user_email'     => $ticket->user?->email ?? '—',
                'user_url'       => $ticket->user ? route('admin.users.show', $ticket->user) : null,
                'messages'       => $ticket->messages->map(fn (SupportTicketMessage $m) => [
                    'id'          => $m->id,
                    // Users are soft-deletable; never let a missing author 500 the thread.
                    'author'      => $m->user?->name ?? 'Unknown',
                    'initial'     => strtoupper(mb_substr($m->user?->name ?? '?', 0, 1)),
                    'body'        => $m->body,
                    'is_staff'    => (bool) $m->is_staff_reply,
                    'is_internal' => (bool) $m->is_internal_note,
                    'attachment'  => $m->hasAttachment() ? $m->attachment_name : null,
                    'created'     => $m->created_at?->format('d M Y, H:i') ?? '—',
                ])->values(),
            ],
            'staff'      => User::whereHas('roles', fn ($q) => $q->where('is_admin_role', true))
                ->orderBy('name')->get(['id', 'name']),
            'statuses'   => $this->statusOptions(),
            'priorities' => $this->priorityOptions(),
        ]);
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

    /**
     * Queue tabs. `waiting_for_staff` is included even though the Blade omitted
     * it — TicketService::reply() sets it whenever a user answers, so those
     * tickets (the ones actually awaiting staff) were only visible under "All".
     *
     * @return list<array{value:string,label:string}>
     */
    private function statusTabs(): array
    {
        return [
            ['value' => 'open',             'label' => 'Open'],
            ['value' => 'waiting_for_staff', 'label' => 'Waiting on Staff'],
            ['value' => 'in_progress',      'label' => 'In Progress'],
            ['value' => 'waiting_for_user', 'label' => 'Waiting on User'],
            ['value' => 'resolved',         'label' => 'Resolved'],
            ['value' => 'closed',           'label' => 'Closed'],
            ['value' => 'all',              'label' => 'All'],
        ];
    }

    /**
     * Statuses staff may set by hand — mirrors the status() `in:` rule, which
     * deliberately excludes waiting_for_staff (the service owns that transition).
     *
     * @return list<array{value:string,label:string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'open',             'label' => 'Open'],
            ['value' => 'in_progress',      'label' => 'In Progress'],
            ['value' => 'waiting_for_user', 'label' => 'Waiting on User'],
            ['value' => 'resolved',         'label' => 'Resolved'],
            ['value' => 'closed',           'label' => 'Closed'],
        ];
    }

    /** @return list<array{value:string,label:string}> */
    private function priorityOptions(): array
    {
        return [
            ['value' => 'low',    'label' => 'Low'],
            ['value' => 'normal', 'label' => 'Normal'],
            ['value' => 'high',   'label' => 'High'],
            ['value' => 'urgent', 'label' => 'Urgent'],
        ];
    }
}
