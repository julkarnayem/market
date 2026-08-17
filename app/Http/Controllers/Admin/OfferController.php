<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Support\Money;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function index()
    {
        $status = request('status', 'all');

        $offers = Offer::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['asset', 'buyer', 'seller'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Offers/Index', [
            'offers' => $offers->through(fn (Offer $o) => [
                'id'          => $o->id,
                'asset_title' => $o->asset?->title ?? '—',
                'buyer_name'  => $o->buyer?->name ?? '—',
                'seller_name' => $o->seller?->name ?? '—',
                'amount'      => Money::format((int) $o->amount),
                'status'      => $o->status->value,
                'expires'     => $o->expires_at?->format('d M, H:i') ?? '—',
                'created'     => $o->created_at->format('d M Y'),
            ]),
            'filters' => [
                'status' => $status,
            ],
            'statuses' => array_map(
                fn ($s) => ['value' => $s, 'label' => ucfirst($s)],
                ['pending', 'accepted', 'rejected', 'expired'],
            ),
        ]);
    }
}
