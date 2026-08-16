<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index()
    {
        $tickets = Auth::user()->supportTickets()
            ->withCount('messages')->latest('last_reply_at')->paginate(15);
        return view('dashboard.tickets', compact('tickets'));
    }

    public function create() { return view('dashboard.tickets-create'); }

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
        $ticket->load(['messages.user','assignee']);
        return view('dashboard.tickets-show', compact('ticket'));
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
