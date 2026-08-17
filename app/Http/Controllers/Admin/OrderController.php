<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Money;
use Inertia\Inertia;

class OrderController extends Controller
{
    /** Status values offered by the index filter — the order lifecycle. */
    private const STATUSES = ['pending_payment', 'delivery_pending', 'delivered', 'completed', 'disputed', 'refunded'];

    public function index()
    {
        $status = request('status', 'all');

        $orders = Order::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(request('q'), fn ($q) => $q->where('order_number', 'like', '%'.request('q').'%'))
            ->with(['asset', 'buyer', 'seller'])
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders->through(fn (Order $o) => [
                'id'              => $o->id,
                'order_number'    => $o->order_number,
                'asset_title'     => $o->asset?->title ?? '—',
                'buyer_name'      => $o->buyer?->name ?? '—',
                'seller_name'     => $o->seller?->name ?? '—',
                'total_formatted' => Money::format((int) $o->buyer_total),
                'status'          => $o->status->value,
                'payment_status'  => $o->payment_status,
                'created'         => $o->created_at->format('d M Y'),
                'url'             => route('admin.orders.show', $o),
            ]),
            'filters' => [
                'q'      => (string) request('q', ''),
                'status' => $status,
            ],
            'statuses' => array_map(
                fn ($s) => ['value' => $s, 'label' => ucwords(str_replace('_', ' ', $s))],
                self::STATUSES,
            ),
        ]);
    }

    public function show(Order $order)
    {
        // Load only what the detail view renders. The Blade over-eager-loaded
        // asset.coverImage, statusHistory.changer and the whole conversation
        // thread, none of which this page shows.
        $order->load(['asset', 'buyer', 'seller', 'statusHistory', 'latestPayment', 'delivery']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'order_number'        => $order->order_number,
                'status'              => $order->status->value,
                'payment_status'      => $order->payment_status,
                'asset_title'         => $order->asset?->title ?? '—',
                'buyer_name'          => $order->buyer?->name ?? '—',
                'seller_name'         => $order->seller?->name ?? '—',
                'quantity'            => $order->quantity,
                'buyer_total'         => Money::format((int) $order->buyer_total),
                'seller_earning'      => Money::format((int) $order->seller_earning),
                'platform_commission' => Money::format((int) $order->platform_commission),
            ],
            'timeline' => $order->statusHistory->sortBy('created_at')->values()->map(fn ($h) => [
                'id'        => $h->id,
                'to_status' => $h->to_status,
                'note'      => $h->note,
                'at'        => $h->created_at?->format('d M, H:i'),
            ]),
            'delivery' => $order->delivery ? [
                'note' => $order->delivery->delivery_note,
            ] : null,
            'payment' => $order->latestPayment ? [
                'gateway'        => $order->latestPayment->gateway,
                'status'         => $order->latestPayment->status,
                'amount'         => Money::format((int) $order->latestPayment->amount),
                'transaction_id' => $order->latestPayment->gateway_transaction_id,
                'paid_at'        => $order->paid_at?->format('d M Y, H:i'),
            ] : null,
        ]);
    }
}
