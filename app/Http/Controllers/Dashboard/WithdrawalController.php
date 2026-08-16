<?php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\SettingsService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $service,
        private readonly SettingsService   $settings,
    ) {}

    public function index()
    {
        $wallet      = Auth::user()->wallet;
        $withdrawals = Auth::user()->withdrawals()->latest()->paginate(15);
        $minBdt      = Money::toBdt($this->settings->minWithdrawal());
        $fee         = $this->settings->withdrawalFee();
        return view('dashboard.withdrawals', compact('wallet','withdrawals','minBdt','fee'));
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
