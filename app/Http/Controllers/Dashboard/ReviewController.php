<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function create(Order $order)
    {
        // Only buyer can review
        abort_unless($order->buyer_user_id === Auth::id(), 403);

        // Order must be delivered or completed
        abort_unless(
            in_array($order->delivery_status, ['confirmed','auto_confirmed']) ||
            $order->status->value === 'completed',
            403,
            'You can only review after delivery is confirmed.'
        );

        // No duplicate review
        abort_if(Review::where('order_id', $order->id)->exists(), 403, 'You already reviewed this order.');

        return view('dashboard.orders.review', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        abort_unless($order->buyer_user_id === Auth::id(), 403);

        // Must be delivered or completed
        abort_unless(
            in_array($order->delivery_status, ['confirmed','auto_confirmed']) ||
            $order->status->value === 'completed',
            403,
            'You can only review after delivery is confirmed.'
        );

        // No duplicate
        abort_if(Review::where('order_id', $order->id)->exists(), 403, 'You already reviewed this order.');

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        Review::create([
            'order_id'    => $order->id,
            'reviewer_id' => Auth::id(),
            'seller_id'   => $order->seller_user_id,
            'asset_id'    => $order->asset_id,
            'rating'      => $data['rating'],
            'comment'     => $data['comment'] ?? null,
        ]);

        return redirect()->route('dashboard.orders.show', $order)
            ->with('success', 'Thank you for your review!');
    }
}
