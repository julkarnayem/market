<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletSvc) {}

    public function index()
    {
        $this->authorize('payments.view');
        $q = request('q');

        $wallets = Wallet::with('user')
            ->when($q, fn ($query) => $query->whereHas(
                'user',
                fn ($u) => $u->where('name', 'like', '%'.$q.'%'),
            ))
            ->orderByDesc('available_balance')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Wallets/Index', [
            'wallets' => $wallets->through(fn (Wallet $w) => [
                'id'         => $w->id,
                'user_name'  => $w->user?->name ?? '—',
                'user_email' => $w->user?->email ?? '—',
                'available'  => Money::format($w->available_balance),
                'pending'    => Money::format($w->pending_balance),
                'total'      => Money::format($w->totalBalance()),
                'url'        => route('admin.wallets.show', $w),
            ]),
            'filters' => [
                'q' => $q,
            ],
        ]);
    }

    public function show(Wallet $wallet)
    {
        $this->authorize('payments.view');
        $wallet->load('user');
        $transactions = $wallet->transactions()->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Wallets/Show', [
            'wallet' => [
                'id'         => $wallet->id,
                'user_name'  => $wallet->user?->name ?? '—',
                'user_email' => $wallet->user?->email ?? '—',
                'available'  => Money::format($wallet->available_balance),
                'pending'    => Money::format($wallet->pending_balance),
                'total'      => Money::format($wallet->totalBalance()),
            ],
            'transactions' => $transactions->through(fn (WalletTransaction $tx) => [
                'id'               => $tx->id,
                'date'             => $tx->created_at->format('d M Y, H:i'),
                'type'             => $tx->type?->value ?? '—',
                // Credit shows a leading '+'; Money::format already renders '-' for debits.
                'amount_formatted' => ($tx->amount > 0 ? '+' : '').Money::format($tx->amount),
                'is_credit'        => $tx->amount > 0,
                'available_after'  => Money::format($tx->available_after),
                'description'      => $tx->description ?? '—',
            ]),
        ]);
    }

    public function adjust(Request $request, Wallet $wallet)
    {
        $this->authorize('payments.view');
        $data = $request->validate([
            'amount_bdt' => 'required|numeric|min:-999999|max:999999',
            'reason'     => 'required|string|min:10|max:500',
        ]);
        $signedPoisha = Money::toPoisha($data['amount_bdt']);
        abort_if($signedPoisha === 0, 422, 'Adjustment amount cannot be zero.');
        $this->walletSvc->adminAdjust($wallet->user, $signedPoisha, $data['reason'], Auth::user());

        return back()->with(
            'success',
            'Wallet adjusted: '.Money::format(abs($signedPoisha)).($signedPoisha > 0 ? ' credited' : ' debited'),
        );
    }
}
