<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $orders = $query->with(['asset.coverImage','buyer','seller'])->latest()->paginate(15);
        return view('dashboard.orders.index', compact('orders','tab','role'));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);
        $order->load(['asset.coverImage','buyer','seller','statusHistory.changer',
                      'delivery','latestPayment','conversation.messages.sender']);
        $isbuyer  = $order->buyer_user_id === Auth::id();
        $isSeller = $order->seller_user_id === Auth::id();
        return view('dashboard.orders.show', compact('order','isbuyer','isSeller'));
    }

    public function deliverForm(Order $order)
    {
        $this->authorize('deliver', $order);
        return view('dashboard.orders.deliver', compact('order'));
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
        return view('dashboard.orders.dispute', compact('order'));
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
