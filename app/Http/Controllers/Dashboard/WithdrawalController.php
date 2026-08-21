<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\WithdrawalMethod;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreWithdrawalRequest;
use App\Models\Withdrawal;
use App\Services\SettingsService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $service,
        private readonly SettingsService   $settings,
    ) {}

    public function index()
    {
        $user = Auth::user();
        // Queried through the relation rather than read off $user->wallet: the
        // authenticated instance may already have the relation loaded from earlier
        // in the request, from before a debit, and the page must never render a
        // stale balance.
        $wallet    = $user->wallet()->first();
        $available = (int) ($wallet?->available_balance ?? 0);
        $pending   = (int) ($wallet?->pending_balance ?? 0);
        $minPoisha = $this->settings->minWithdrawal();   // POISHA
        $minBdt    = Money::toBdt($minPoisha);
        $fee       = $this->settings->withdrawalFee();   // POISHA

        // What has actually been paid out, so the page can show it alongside the
        // balances. Aggregated in one query rather than summed in the view.
        $totalWithdrawn = (int) $user->withdrawals()
            ->where('status', WithdrawalStatus::Completed->value)
            ->sum('amount');

        return Inertia::render('Dashboard/Withdrawals', [
            'availableFormatted' => Money::format($available),
            'minBdt'             => $minBdt,
            'minBdtFormatted'    => number_format($minBdt, 2),
            'feeFormatted'       => Money::format($fee),
            'feeBdt'             => Money::toBdt($fee),
            'feeBdtFormatted'    => number_format(Money::toBdt($fee), 2),
            'hasPending'         => $pending > 0,
            'pendingFormatted'   => Money::format($pending),
            'canWithdraw'        => $available >= $minPoisha,
            'maxBdt'             => Money::toBdt($available),
            'totalWithdrawnFormatted' => Money::format($totalWithdrawn),
            // Drives the method selector; the same enum backs the request rules,
            // so the form can never offer a method validation would reject.
            'methods'            => WithdrawalMethod::options(),
            'withdrawals'        => $user->withdrawals()->latest()->paginate(15)
                ->through(fn (Withdrawal $w) => [
                    'id'               => $w->id,
                    'reference'        => $w->reference(),
                    'amount_formatted' => Money::format($w->amount),
                    'fee_formatted'    => Money::format($w->fee),
                    'net_formatted'    => Money::format($w->net_amount),
                    'method_label'     => $w->methodLabel(),
                    // Masked, never the raw account number.
                    'masked_number'    => $w->maskedAccount(),
                    'status'           => $w->status->value,
                    'status_label'     => $w->status->label(),
                    'date'             => $w->created_at->format('d M Y'),
                    'processed_at'     => $w->processed_at?->format('d M Y')
                        ?? $w->rejected_at?->format('d M Y')
                        ?? $w->cancelled_at?->format('d M Y'),
                    'rejection_reason' => $w->rejection_reason,
                    'can_cancel'       => $w->isCancellable(),
                ]),
        ]);
    }

    public function store(StoreWithdrawalRequest $request)
    {
        $method = WithdrawalMethod::from($request->validated('method'));

        // Only the amount and the account fields come from the request; the user
        // is the authenticated one and the balance is the service's business.
        $withdrawal = $this->service->request(
            Auth::user(),
            Money::toPoisha($request->validated('amount_bdt')),
            $method,
            $request->accountDetails(),
            $request->validated('client_request_id'),
        );

        return redirect()->route('dashboard.withdrawals')->with(
            'success',
            "Withdrawal request {$withdrawal->reference()} submitted. Admin will process it within 1–2 business days.",
        );
    }

    /** The user takes back their own pending request. */
    public function cancel(Withdrawal $withdrawal)
    {
        // Ownership is checked here and again inside the service, under the lock.
        abort_unless((int) $withdrawal->user_id === Auth::id(), 403);

        $this->service->cancel($withdrawal, Auth::user());

        return back()->with('success', "Withdrawal {$withdrawal->reference()} cancelled — the funds are back in your wallet.");
    }
}
