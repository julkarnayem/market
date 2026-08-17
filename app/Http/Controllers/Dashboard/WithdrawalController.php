<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\SettingsService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\Request;
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
        $wallet    = Auth::user()->wallet;
        $available = (int) ($wallet?->available_balance ?? 0);
        $pending   = (int) ($wallet?->pending_balance ?? 0);
        $minPoisha = $this->settings->minWithdrawal();   // POISHA
        $minBdt    = Money::toBdt($minPoisha);
        $fee       = $this->settings->withdrawalFee();   // POISHA

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
            'withdrawals'        => Auth::user()->withdrawals()->latest()->paginate(15)
                ->through(fn ($w) => [
                    'id'               => $w->id,
                    'amount_formatted' => Money::format($w->amount),
                    'fee_formatted'    => Money::format($w->fee),
                    'net_formatted'    => Money::format($w->net_amount),
                    'provider'         => $w->mfs_provider,
                    'masked_number'    => $w->maskedNumber(),
                    'status'           => $w->status->value,
                    'date'             => $w->created_at->format('d M Y'),
                    'rejection_reason' => $w->rejection_reason,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $minBdt = Money::toBdt($this->settings->minWithdrawal());
        $data = $request->validate([
            'amount_bdt'   => "required|numeric|min:{$minBdt}|max:999999",
            'mfs_provider' => 'required|in:bkash,nagad,rocket,upay',
            'mfs_number'   => 'required|string|min:11|max:15|regex:/^01[3-9]\d{8}$/',
        ], ['mfs_number.regex' => 'Enter a valid Bangladeshi mobile number (e.g. 01XXXXXXXXX)']);

        $amountPoisha = Money::toPoisha($data['amount_bdt']);

        $this->service->request(Auth::user(), $amountPoisha, $data['mfs_provider'], $data['mfs_number']);

        return redirect()->route('dashboard.withdrawals')->with('success', 'Withdrawal request submitted. Admin will process it within 1–2 business days.');
    }
}
