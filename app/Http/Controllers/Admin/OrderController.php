<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $status = request('status','all');
        $orders = Order::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when(request('q'), fn($q) => $q->where('order_number', 'like', '%'.request('q').'%'))
            ->with(['asset','buyer','seller'])
            ->latest()->paginate(20);
        return view('admin.orders.index', compact('orders','status'));
    }

    public function show(Order $order)
    {
        $order->load(['asset.coverImage','buyer','seller','statusHistory.changer','latestPayment','delivery','conversation.messages.sender']);
        return view('admin.orders.show', compact('order'));
    }
}
