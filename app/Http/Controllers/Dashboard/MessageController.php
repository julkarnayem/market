<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $service) {}

    public function index()
    {
        $user = Auth::user();

        // Get all conversations this user participates in
        $conversations = Conversation::whereHas('participants', fn($q) => $q->where('users.id', $user->id))
            ->with([
                'order.asset.coverImage',
                'participants' => fn($q) => $q->where('users.id', '!=', $user->id)->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->paginate(30);

        // Compute per-conversation unread counts efficiently
        $unreadMap = [];
        foreach ($conversations as $conv) {
            $lastRead = $conv->participants()
                ->where('conversation_participants.conversation_id', $conv->id)
                ->where('users.id', $user->id)
                ->value('last_read_at');
            $q = Message::where('conversation_id', $conv->id)->where('sender_user_id', '!=', $user->id);
            if ($lastRead) $q->where('created_at', '>', $lastRead);
            $unreadMap[$conv->id] = $q->count();
        }

        $selectedConversation = null;
        $messages             = collect();

        if ($convId = request('conversation')) {
            $selectedConversation = Conversation::whereHas('participants', fn($q) => $q->where('users.id', $user->id))
                ->with(['order.asset','order.seller','order.buyer','participants'])
                ->findOrFail($convId);

            // Mark read
            $this->service->markRead($selectedConversation, $user);
            $unreadMap[$convId] = 0;

            // Load recent 50 messages (cursor pagination approach)
            $messages = $selectedConversation->activeMessages()
                ->with('sender','replyTo.sender')
                ->latest()
                ->limit(50)
                ->get()
                ->reverse()
                ->values();
        }

        $totalUnread     = collect($unreadMap)->sum();
        $broadcastDriver = config('broadcasting.default','null');
        $isRealtimeReady = $broadcastDriver !== 'null';

        return view('dashboard.messages', compact(
            'conversations','selectedConversation','messages','unreadMap','totalUnread','isRealtimeReady'
        ));
    }

    /** POST /messages/{conversation}/send */
    public function send(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->hasParticipant(Auth::id()), 403);

        $data = $request->validate([
            'body'              => 'required_without:attachment|nullable|string|max:5000',
            'attachment'        => 'nullable|file|max:10240',
            'client_message_id' => 'nullable|string|max:100',
            'reply_to_id'       => 'nullable|integer',
        ]);

        $msg = $this->service->send(
            $conversation,
            Auth::user(),
            $data['body'] ?? '',
            $data['client_message_id'] ?? null,
            $request->file('attachment'),
            $data['reply_to_id'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'id'         => $msg->id,
                'body'       => $msg->safeBody(),
                'created_at' => $msg->created_at->format('H:i'),
                'sender_id'  => $msg->sender_user_id,
            ]);
        }
        return back();
    }

    /** AJAX: unread count for badge */
    public function unreadCount()
    {
        $user  = Auth::user();
        $count = Conversation::whereHas('participants', fn($q) => $q->where('users.id', $user->id))
            ->get()
            ->sum(fn($c) => $c->unreadCountFor($user->id));
        return response()->json(['count' => $count]);
    }

    /** AJAX: poll for new messages (fallback when WebSocket not configured) */
    public function poll(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->hasParticipant(Auth::id()), 403);
        $since  = $request->query('since'); // ISO timestamp or null
        $query  = $conversation->activeMessages()->with('sender');
        if ($since) $query->where('created_at', '>', $since);
        $messages = $query->oldest()->limit(50)->get();
        return response()->json($messages->map(fn($m) => [
            'id'          => $m->id,
            'sender_id'   => $m->sender_user_id,
            'sender_name' => $m->sender->name,
            'body'        => $m->safeBody(),
            'is_system'   => $m->is_system,
            'created_at'  => $m->created_at->toIso8601String(),
            'time_label'  => $m->created_at->format('H:i'),
        ]));
    }

    /** Report a message */
    public function reportMessage(Request $request, Message $message)
    {
        $data = $request->validate([
            'reason'      => 'required|in:scam,abuse,threat,spam,prohibited,other',
            'description' => 'nullable|string|max:1000',
        ]);
        $this->service->report($message, Auth::user(), $data['reason'], $data['description'] ?? '');
        return back()->with('success', 'Message reported. Our team will review it.');
    }

    /** Serve private attachment */
    public function attachment(Message $message)
    {
        abort_unless($message->conversation->hasParticipant(Auth::id()), 403);
        abort_unless($message->attachment_path, 404);
        return Storage::disk('private')->download($message->attachment_path, $message->attachment_name ?? 'file');
    }
}
