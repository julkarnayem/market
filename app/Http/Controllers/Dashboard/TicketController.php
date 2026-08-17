<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index()
    {
        $tickets = Auth::user()->supportTickets()
            ->latest('last_reply_at')->paginate(15)->withQueryString();

        return Inertia::render('Dashboard/Tickets/Index', [
            'tickets' => $tickets->through(fn (SupportTicket $t) => [
                'id'             => $t->id,
                'subject'        => $t->subject,
                'status'         => $t->status->value,
                'priority_label' => ucfirst($t->priority),
                'priority_color' => $t->priorityColor(),
                'updated_human'  => $t->updated_at->diffForHumans(),
            ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/Tickets/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject'       => 'required|string|max:255',
            'category'      => 'required|string|in:account,verification,listing,order,payment,withdrawal,dispute,promotion,technical,other',
            'priority'      => 'required|in:low,normal,high',
            'message'       => 'required|string|max:5000',
            'attachment'    => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,txt,zip',
            'order_id'      => 'nullable|integer|exists:orders,id',
            'asset_id'      => 'nullable|integer|exists:assets,id',
            'withdrawal_id' => 'nullable|integer|exists:withdrawals,id',
        ]);
        $ticket = $this->service->create(Auth::user(), $data, $request->file('attachment'));
        return redirect()->route('dashboard.tickets.show', $ticket)
            ->with('success', "Ticket #{$ticket->reference} created. We will respond within 24 hours.");
    }

    public function show(SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === Auth::id(), 403);
        // Internal notes are staff-only (Admin\TicketController::internalNote writes
        // them with is_internal_note=true); they must never reach the ticket owner.
        $ticket->load([
            'messages' => fn ($q) => $q->where('is_internal_note', false)->with('user'),
            'assignee', 'order', 'asset', 'withdrawal',
        ]);

        $links = [];
        if ($ticket->order) {
            $links[] = ['icon' => '📦', 'color' => 'brand', 'label' => 'Order '.$ticket->order->order_number, 'href' => route('dashboard.orders.show', $ticket->order)];
        }
        if ($ticket->asset) {
            $links[] = ['icon' => '🏷️', 'color' => 'slate', 'label' => Str::limit($ticket->asset->title, 40), 'href' => route('marketplace.show', $ticket->asset->slug)];
        }
        if ($ticket->withdrawal) {
            $links[] = ['icon' => '🏦', 'color' => 'mint', 'label' => 'Withdrawal #'.$ticket->withdrawal_id, 'href' => route('dashboard.withdrawals')];
        }

        return Inertia::render('Dashboard/Tickets/Show', [
            'ticket' => [
                'id'             => $ticket->id,
                'reference'      => $ticket->reference,
                'subject'        => $ticket->subject,
                'status'         => $ticket->status->value,
                'priority_label' => ucfirst($ticket->priority),
                'priority_color' => $ticket->priorityColor(),
                'assignee_name'  => $ticket->assignee?->name,
                'can_reply'      => $ticket->isOpen(),
                'links'          => $links,
                'messages'       => $ticket->messages->map(fn (SupportTicketMessage $m) => [
                    'id'            => $m->id,
                    'is_staff'      => (bool) $m->is_staff_reply,
                    'author'        => $m->is_staff_reply ? 'Support Team' : $m->user->name,
                    'initial'       => strtoupper(mb_substr($m->user->name, 0, 1)),
                    'body'          => $m->body,
                    'created_human' => $m->created_at->diffForHumans(),
                ])->values(),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === Auth::id(), 403);
        $data = $request->validate([
            'body'       => 'required|string|max:5000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,txt,zip',
        ]);
        $this->service->reply($ticket, Auth::user(), $data['body'], $request->file('attachment'), false);
        return back()->with('success', 'Reply sent.');
    }
}
