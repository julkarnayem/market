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
use Illuminate\Support\Str;
use Inertia\Inertia;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $service) {}

    public function index()
    {
        $user = Auth::user();

        // Conversations this user participates in, newest activity first. Only
        // the *other* participant is eager-loaded (the list shows their name).
        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with([
                'order.asset',
                'participants' => fn ($q) => $q->where('users.id', '!=', $user->id)->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $selectedId         = null;
        $activeConversation = null;
        $messages           = [];

        if ($convId = request('conversation')) {
            $selected = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
                ->with(['order.asset', 'participants'])
                ->findOrFail($convId);

            $this->service->markRead($selected, $user);
            $selectedId = $selected->id;

            $other = $selected->participants->firstWhere('id', '!=', $user->id);
            $order = $selected->order;

            $activeConversation = [
                'id'            => $selected->id,
                'other_name'    => $other?->name ?? 'Unknown',
                'other_initial' => mb_strtoupper(mb_substr($other?->name ?? '?', 0, 1)),
                'order_number'  => $order?->order_number,
                'asset_title'   => $order?->asset?->title ?? '',
                'order_status'  => $order?->status?->value ?? 'unknown',
                'order_url'     => $order ? route('dashboard.orders.show', $order) : null,
            ];

            // Latest 50, oldest-first for chronological display. activeMessages()
            // already excludes soft-deleted rows, so there is no "deleted" state.
            $messages = $selected->activeMessages()
                ->with('sender', 'replyTo.sender')
                ->latest()->limit(50)->get()
                ->reverse()->values()
                ->map(fn (Message $m) => [
                    'id'             => $m->id,
                    'mine'           => $m->sender_user_id === $user->id,
                    'is_system'      => (bool) $m->is_system,
                    'sender_name'    => $m->sender?->name ?? 'Unknown',
                    'sender_initial' => mb_strtoupper(mb_substr($m->sender?->name ?? '?', 0, 1)),
                    'body'           => $m->body ?? '',
                    'time'           => $m->created_at->format('H:i'),
                    'attachment'     => $m->hasAttachment()
                        ? ['name' => $m->attachment_name ?? 'Attachment', 'url' => route('messages.attachment', $m)]
                        : null,
                    'reply_to'       => $m->replyTo
                        ? ['sender_name' => $m->replyTo->sender?->name ?? 'Unknown', 'excerpt' => Str::limit($m->replyTo->body ?? '', 80)]
                        : null,
                ]);
        }

        return Inertia::render('Dashboard/Messages/Index', [
            'conversations' => $conversations->through(fn (Conversation $c) => [
                'id'                 => $c->id,
                'other_name'         => $c->participants->first()?->name ?? 'Unknown',
                'other_initial'      => mb_strtoupper(mb_substr($c->participants->first()?->name ?? '?', 0, 1)),
                'subtitle'           => $c->order?->asset?->title ?? ('Order #'.$c->order_id),
                'order_status'       => $c->order?->status?->value,
                'order_status_label' => $c->order?->status
                    ? ucwords(str_replace('_', ' ', $c->order->status->value))
                    : '',
                'unread'             => $c->unreadCountFor($user->id),
                'last_human'         => $c->last_message_at?->diffForHumans(null, true),
            ]),
            'selectedId'         => $selectedId,
            'activeConversation' => $activeConversation,
            'messages'           => $messages,
            'isRealtimeReady'    => config('broadcasting.default', 'null') !== 'null',
        ]);
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

        // A bare JSON client (none today) still gets JSON; an Inertia POST must
        // fall through to back() so the router follows the redirect and reloads.
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
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
