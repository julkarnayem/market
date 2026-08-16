<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletSvc) {}

    public function index()
    {
        $this->authorize('payments.view');
        $wallets = Wallet::with('user')
            ->when(request('q'), fn($q) => $q->whereHas('user', fn($u) => $u->where('name','like','%'.request('q').'%')))
            ->orderByDesc('available_balance')->paginate(20);
        return view('admin.wallets', compact('wallets'));
    }

    public function show(Wallet $wallet)
    {
        $this->authorize('payments.view');
        $wallet->load('user');
        $transactions = $wallet->transactions()->latest()->paginate(25);
        return view('admin.wallets-show', compact('wallet','transactions'));
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
        return back()->with('success', 'Wallet adjusted: ' . Money::format(abs($signedPoisha)) . ($signedPoisha > 0 ? ' credited' : ' debited'));
    }
}
