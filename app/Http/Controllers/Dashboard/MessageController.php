<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\AssetStatus;
use App\Enums\InventoryType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\Offer;
use App\Services\MessageService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
                'asset',
                'participants' => fn ($q) => $q->where('users.id', '!=', $user->id)->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $selectedId         = null;
        $activeConversation = null;
        $messages           = [];
        $listing            = null;
        $offers             = [];
        $canOffer           = false;

        if ($convId = request('conversation')) {
            $selected = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
                ->with(['order.asset', 'asset.coverImage', 'participants'])
                ->findOrFail($convId);

            $this->service->markRead($selected, $user);
            $selectedId = $selected->id;

            $other = $selected->participants->firstWhere('id', '!=', $user->id);
            $order = $selected->order;

            $activeConversation = [
                'id'            => $selected->id,
                'other_name'    => $other?->name ?? 'Unknown',
                'other_initial' => mb_strtoupper(mb_substr($other?->name ?? '?', 0, 1)),
                // The header's presence dot. Nothing here is realtime yet, so it
                // reflects the last login we recorded, not a live socket.
                'other_online'  => (bool) $other?->last_login_at?->gt(now()->subMinutes(5)),
                'other_seen'    => $other?->last_login_at?->diffForHumans(null, true),
                'order_number'  => $order?->order_number,
                'asset_title'   => $selected->contextAsset()?->title ?? '',
                'order_status'  => $order?->status?->value ?? 'unknown',
                'order_url'     => $order ? route('dashboard.orders.show', $order) : null,
            ];

            // The listing this thread is about — the context card above the
            // messages. It survives the order being created, because the order
            // conversation carries the same asset_id.
            $contextAsset = $selected->contextAsset();
            $listing      = $contextAsset ? [
                'title'           => $contextAsset->title,
                'price_formatted' => Money::format((int) $contextAsset->price),
                'inventory_type'  => $contextAsset->inventoryType()->value,
                'inventory_label' => $contextAsset->inventoryType()->label(),
                // Caps the offer form's quantity field. Unlimited has no ceiling,
                // and Single is always exactly one.
                'max_quantity'    => match ($contextAsset->inventoryType()) {
                    InventoryType::Single    => 1,
                    InventoryType::Unlimited => 9999,
                    default                  => max(1, (int) $contextAsset->available_quantity),
                },
                'cover'           => $contextAsset->coverImage?->url(),
                'url'             => route('marketplace.show', $contextAsset->slug),
            ] : null;

            // Custom offers are chat-only, so they load with the thread. They are
            // never part of a listing's public bid history.
            $offers = $selected->offers()
                ->with('asset')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Offer $offer) => self::mapOffer($offer, $user))
                ->keyBy('id')
                ->all();

            // Either party may send one, on any inventory type — but only while
            // the listing is actually live.
            $canOffer = $contextAsset !== null
                && $user->canTransact()
                && $contextAsset->status === AssetStatus::Published;

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
                    'type'           => $m->message_type ?? 'text',
                    // Set on custom-offer cards so the bubble renders the offer
                    // from the `offers` map instead of a plain body.
                    'offer_id'       => isset($m->metadata['offer_id']) ? (int) $m->metadata['offer_id'] : null,
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
                // A Contact Seller thread has a listing but no order yet, so the
                // subtitle comes from whichever context the thread carries.
                'subtitle'           => $c->contextAsset()?->title
                    ?? ($c->order_id ? 'Order #'.$c->order_id : 'Conversation'),
                'order_status'       => $c->order?->status?->value,
                'order_status_label' => $c->order?->status
                    ? ucwords(str_replace('_', ' ', $c->order->status->value))
                    : '',
                'unread'             => $c->unreadCountFor($user->id),
                'last_human'         => $c->last_message_at?->diffForHumans(null, true),
            ]),
            'selectedId'         => $selectedId,
            'activeConversation' => $activeConversation,
            'listing'            => $listing,
            'messages'           => $messages,
            'offers'             => $offers,
            'canOffer'           => $canOffer,
            // BROADCAST_DRIVER=null arrives as PHP null, not the string "null" —
            // env() casts it — so the string comparison alone reported a
            // broadcaster that does not exist and switched polling off, leaving
            // the chat with no way to update at all.
            'isRealtimeReady'    => !in_array((string) config('broadcasting.default'), ['', 'null'], true),
        ]);
    }

    /**
     * One custom offer, as the chat card needs it.
     *
     * Every capability is a server decision. Note that "accept" and "pay" are
     * separate: whoever did not create the offer accepts it, but only the buyer
     * ever pays — a seller whose own offer was accepted gets no Pay button.
     */
    private static function mapOffer(Offer $offer, $user): array
    {
        $gate     = Gate::forUser($user);
        $isBuyer  = $offer->isPayer($user->id);
        $canRespond = $gate->allows('respond', $offer);

        return [
            'id'                => $offer->id,
            'amount_formatted'  => Money::format((int) $offer->amount),
            'quantity'          => (int) $offer->quantity,
            'delivery_days'     => $offer->delivery_days,
            'note'              => $offer->buyer_message,
            'status'            => $offer->status->value,
            'status_label'      => $offer->status->label(),
            'is_pending'        => $offer->isPending() && !$offer->isExpired(),
            'mine'              => $offer->isCreator($user->id),
            'expires_in_seconds'=> $offer->isPending() ? $offer->timeRemainingSeconds() : 0,
            'can_accept'        => $canRespond,
            'can_decline'       => $canRespond,
            'can_cancel'        => $gate->allows('cancel', $offer),
            // The buyer's accept goes straight to checkout, so the button says
            // "Accept & Pay"; the seller's accept just closes the negotiation.
            'accept_is_pay'     => $canRespond && $isBuyer,
            'can_pay'           => $gate->allows('pay', $offer),
            'pay_url'           => $gate->allows('pay', $offer) && $offer->asset
                ? route('checkout.show', ['slug' => $offer->asset->slug, 'offer' => $offer->id])
                : null,
            'accept_url'        => route('offers.accept', $offer),
            'reject_url'        => route('offers.reject', $offer),
            'cancel_url'        => route('offers.cancel', $offer),
        ];
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

    /**
     * AJAX: poll for new messages (fallback when WebSocket not configured).
     *
     * Custom offers change state without a new message being sent — the other
     * party clicks Accept and nothing else happens — so the poll carries the
     * current offer state alongside new messages. That is what lets a card flip
     * to "Offer Accepted ✅ / Pay Now" without a page refresh.
     */
    public function poll(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->hasParticipant(Auth::id()), 403);
        $user   = Auth::user();
        $since  = $request->query('since'); // ISO timestamp or null
        $query  = $conversation->activeMessages()->with('sender');
        if ($since) $query->where('created_at', '>', $since);
        $messages = $query->oldest()->limit(50)->get();

        $offers = $conversation->offers()->with('asset')->orderByDesc('id')->get()
            ->map(fn (Offer $offer) => self::mapOffer($offer, $user))
            ->keyBy('id');

        return response()->json([
            'messages' => $messages->map(fn($m) => [
                'id'          => $m->id,
                'sender_id'   => $m->sender_user_id,
                'sender_name' => $m->sender->name,
                'body'        => $m->safeBody(),
                'is_system'   => $m->is_system,
                'type'        => $m->message_type ?? 'text',
                'offer_id'    => isset($m->metadata['offer_id']) ? (int) $m->metadata['offer_id'] : null,
                'created_at'  => $m->created_at->toIso8601String(),
                'time_label'  => $m->created_at->format('H:i'),
            ])->values(),
            'offers'   => $offers,
        ]);
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
