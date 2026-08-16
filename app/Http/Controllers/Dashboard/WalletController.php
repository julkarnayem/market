<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function index()
    {
        $user         = Auth::user();
        $wallet       = $user->wallet;
        $transactions = $wallet?->transactions()->latest()->paginate(20) ?? collect();

        // Aggregate stats
        $totalEarned  = (int) ($wallet?->transactions()
            ->whereIn('type', [TransactionType::SellerEarningReleased->value, TransactionType::Release->value])
            ->where('amount', '>', 0)->sum('amount') ?? 0);
        $totalWithdrawn = (int) abs($wallet?->transactions()
            ->whereIn('type', [TransactionType::WithdrawalReserve->value, TransactionType::Withdrawal->value])
            ->sum('amount') ?? 0);
        $totalPending = $wallet?->pending_balance ?? 0;

        return view('dashboard.wallet', compact('wallet','transactions','totalEarned','totalWithdrawn','totalPending'));
    }
}
