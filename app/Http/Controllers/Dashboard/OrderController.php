<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function index()
    {
        $user  = Auth::user();
        $tab   = request('tab', 'all');
        $role  = request('role', 'buyer'); // buyer | seller

        $query = $role === 'seller'
            ? Order::where('seller_user_id', $user->id)
            : Order::where('buyer_user_id', $user->id);

        if ($tab !== 'all') $query->where('status', $tab);

        $orders = $query->with(['asset.coverImage','buyer','seller'])->latest()->paginate(15)->withQueryString();

        return Inertia::render('Dashboard/Orders/Index', [
            'role'   => $role,
            'tab'    => $tab,
            'orders' => $orders->through(fn ($o) => [
                'id'              => $o->id,
                'order_number'    => $o->order_number,
                'asset_title'     => $o->asset->title,
                'party_name'      => $role === 'seller' ? $o->buyer->name : $o->seller->name,
                'quantity'        => $o->quantity,
                'total_formatted' => Money::format($o->buyer_total),
                'status'          => $o->status->value,
                'payment_status'  => $o->payment_status,
                'date'            => $o->created_at->format('d M Y'),
            ]),
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);
        $order->load(['asset.coverImage','asset.category','buyer','seller','statusHistory',
                      'delivery','latestPayment','conversation.messages']);
        $userId   = Auth::id();
        $isBuyer  = $order->buyer_user_id === $userId;
        $isSeller = $order->seller_user_id === $userId;

        return Inertia::render('Dashboard/Orders/Show', [
            'order' => [
                'id'                       => $order->id,
                'order_number'             => $order->order_number,
                'status'                   => $order->status->value,
                'payment_status'           => $order->payment_status,
                'delivery_status'          => $order->delivery_status,
                'created_full'             => $order->created_at->format('d M Y, H:i'),
                'asset_title'              => $order->asset->title,
                'asset_category'           => $order->asset->category?->name,
                'asset_cover_url'          => $order->asset->coverImage?->url(),
                'unit_price_formatted'     => Money::format($order->unit_price),
                'quantity'                 => $order->quantity,
                'subtotal_formatted'       => Money::format($order->subtotal),
                'buyer_fee_amount'         => (int) $order->buyer_fee_amount,
                'buyer_fee_formatted'      => Money::format($order->buyer_fee_amount),
                'buyer_total_formatted'    => Money::format($order->buyer_total),
                'seller_fee_percent'       => number_format($order->seller_fee_bp / 100, 2),
                'seller_fee_formatted'     => Money::format($order->seller_fee_amount),
                'seller_earning_formatted' => Money::format($order->seller_earning),
                'earning_locked'           => $order->earningIsLocked(),
                'earning_available_at'     => $order->seller_earning_available_at?->format('d M Y, H:i'),
                'can_be_delivered'         => $order->status->canBeDelivered(),
                'can_be_completed'         => $order->status->canBeCompleted(),
                'can_open_dispute'         => $order->status->canOpenDispute(),
                'auto_complete_human'      => $order->auto_complete_at?->diffForHumans(),
            ],
            'isBuyer'  => $isBuyer,
            'isSeller' => $isSeller,
            'delivery' => $order->delivery ? [
                'note'            => $order->delivery->delivery_note,
                'has_attachment'  => (bool) $order->delivery->attachment_path,
                'delivered_human' => $order->delivered_at?->diffForHumans(),
            ] : null,
            'timeline' => $order->statusHistory->sortBy('created_at')->map(fn ($h) => [
                'id'        => $h->id,
                'to_status' => $h->to_status,
                'note'      => $h->note,
                'at'        => $h->created_at?->format('d M, H:i'),
            ])->values(),
            'conversation' => $order->conversation ? [
                'id'       => $order->conversation->id,
                'messages' => $order->conversation->messages->take(5)->map(fn ($m) => [
                    'id'   => $m->id,
                    'body' => $m->body,
                    'mine' => $m->sender_user_id === $userId,
                    'time' => $m->created_at->format('H:i'),
                ])->values(),
            ] : null,
            'participants' => [
                ['role' => 'Buyer',  'name' => $order->buyer->name,  'initial' => strtoupper(substr($order->buyer->name, 0, 1))],
                ['role' => 'Seller', 'name' => $order->seller->name, 'initial' => strtoupper(substr($order->seller->name, 0, 1))],
            ],
            'payment' => $order->latestPayment ? [
                'paid_at_full' => $order->paid_at?->format('d M Y, H:i'),
            ] : null,
            'alreadyReviewed' => Review::where('order_id', $order->id)->exists(),
        ]);
    }

    public function deliverForm(Order $order)
    {
        $this->authorize('deliver', $order);
        return Inertia::render('Dashboard/Orders/Deliver', [
            'order' => [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'asset_title'  => $order->asset->title,
            ],
        ]);
    }

    public function deliver(Request $request, Order $order)
    {
        $this->authorize('deliver', $order);
        $data = $request->validate([
            'delivery_note' => 'required|string|min:10|max:5000',
            'attachment'    => 'nullable|file|max:20480', // 20MB private file
        ]);
        $this->service->deliver($order, Auth::user(), $data['delivery_note'], $request->file('attachment'));
        return redirect()->route('dashboard.orders.show', $order)->with('success', 'Delivery submitted. Buyer has been notified.');
    }

    public function complete(Order $order)
    {
        $this->authorize('complete', $order);
        $this->service->complete($order, Auth::user());
        return redirect()->route('dashboard.orders.show', $order)->with('success', 'Order completed. Seller earnings are being released shortly.');
    }

    public function openDisputeForm(Order $order)
    {
        $this->authorize('openDispute', $order);
        return Inertia::render('Dashboard/Orders/Dispute', [
            'order' => [
                'id'           => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);
    }

    public function openDispute(Request $request, Order $order)
    {
        $this->authorize('openDispute', $order);
        $data = $request->validate(['reason' => 'required|string|min:20|max:2000']);
        $this->service->openDispute($order, Auth::user(), $data['reason']);
        return redirect()->route('dashboard.orders.show', $order)->with('success', 'Dispute opened. Admin will review within 24–48 hours.');
    }

    /** Serve private delivery attachment (authorized participants only) */
    public function deliveryAttachment(Order $order)
    {
        $this->authorize('view', $order);
        $delivery = $order->delivery;
        abort_unless($delivery && $delivery->attachment_path, 404);
        return response()->streamDownload(
            fn() => print(\Illuminate\Support\Facades\Storage::disk('private')->get($delivery->attachment_path)),
            basename($delivery->attachment_path)
        );
    }
}
