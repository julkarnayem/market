<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index()
    {
        $user   = Auth::user();
        $wallet = $user->wallet;

        // A null wallet still needs a paginator so ->through()/Paginated<T>
        // serialization holds (a plain Collection has no ->through()).
        $transactions = $wallet
            ? $wallet->transactions()->latest()->paginate(20)
            : new LengthAwarePaginator([], 0, 20);

        // Aggregate stats
        $totalEarned  = (int) ($wallet?->transactions()
            ->whereIn('type', [TransactionType::SellerEarningReleased->value, TransactionType::Release->value])
            ->where('amount', '>', 0)->sum('amount') ?? 0);
        $totalWithdrawn = (int) abs($wallet?->transactions()
            ->whereIn('type', [TransactionType::WithdrawalReserve->value, TransactionType::Withdrawal->value])
            ->sum('amount') ?? 0);
        $totalPending = (int) ($wallet?->pending_balance ?? 0);

        return Inertia::render('Dashboard/Wallet', [
            'stats' => [
                'available_formatted' => Money::format((int) ($wallet?->available_balance ?? 0)),
                'pending_formatted'   => Money::format($totalPending),
                'earned_formatted'    => Money::format($totalEarned),
                'withdrawn_formatted' => Money::format($totalWithdrawn),
            ],
            'transactions' => $transactions->through(fn ($tx) => [
                'id'                      => $tx->id,
                'datetime'                => $tx->created_at->format('d M Y, H:i'),
                'date'                    => $tx->created_at->format('d M Y'),
                'type_label'              => ucwords(str_replace('_', ' ', $tx->type instanceof TransactionType ? $tx->type->value : $tx->type)),
                'is_credit'               => $tx->amount > 0,
                'amount_formatted'        => ($tx->amount > 0 ? '+' : '').Money::format($tx->amount),
                'balance_after_formatted' => Money::format($tx->available_after),
                'description'             => $tx->description,
            ]),
        ]);
    }
}
