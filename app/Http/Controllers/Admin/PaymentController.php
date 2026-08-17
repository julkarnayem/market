<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Money;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        $status = request('status', 'all');
        $q      = request('q');

        $payments = Payment::query()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($q, fn ($query) => $query->whereHas(
                'order',
                fn ($o) => $o->where('order_number', 'like', '%'.$q.'%'),
            ))
            ->with('order.buyer')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments->through(fn (Payment $p) => [
                'id'             => $p->id,
                'order_number'   => $p->order?->order_number ?? '—',
                // The orders show is already Inertia, so this cross-link is a <Link>.
                'order_url'      => $p->order ? route('admin.orders.show', $p->order) : null,
                'buyer_name'     => $p->order?->buyer?->name ?? '—',
                'amount'         => Money::format((int) $p->amount),
                'gateway'        => $p->gateway ?? '—',
                'transaction_id' => $p->gateway_transaction_id ?? '—',
                'status'         => $p->status,
                'paid_at'        => $p->paid_at?->format('d M Y, H:i') ?? '—',
            ]),
            'filters' => [
                'q'      => $q,
                'status' => $status,
            ],
            'statuses' => array_map(
                fn ($s) => ['value' => $s, 'label' => ucfirst($s)],
                ['pending', 'paid', 'failed', 'cancelled', 'refunded'],
            ),
        ]);
    }
}
