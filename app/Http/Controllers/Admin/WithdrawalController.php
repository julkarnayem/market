<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\AuditLogger;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WithdrawalController extends Controller
{
    /** Status options offered by the index filter (a subset of WithdrawalStatus). */
    private const STATUSES = ['pending', 'approved', 'completed', 'rejected', 'all'];

    public function __construct(
        private readonly WithdrawalService $service,
        private readonly AuditLogger       $audit,
    ) {}

    public function index()
    {
        // The admin middleware only proves this is staff; listing payouts is its
        // own permission, and show() already required it.
        $this->authorize('withdrawals.view');

        $status = request('status', 'pending');

        $withdrawals = Withdrawal::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(request('q'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.request('q').'%')
                ->orWhere('email', 'like', '%'.request('q').'%')))
            ->with('user')
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Withdrawals/Index', [
            'withdrawals' => $withdrawals->through(fn (Withdrawal $w) => [
                'id'             => $w->id,
                'reference'      => $w->reference(),
                'user_name'      => $w->user?->name ?? '—',
                'user_email'     => $w->user?->email ?? '',
                // Which side of the marketplace the money came from, so staff can
                // see at a glance whether this is seller earnings or a buyer refund.
                'user_role'      => $w->user === null ? '—'
                    : ($w->user->sales()->exists() ? 'Seller' : 'Buyer'),
                'amount_formatted' => Money::format((int) $w->amount),
                'fee_formatted'  => Money::format((int) $w->fee),
                'net_formatted'  => Money::format((int) $w->net_amount),
                'provider'       => $w->methodLabel(),
                'account'        => $w->maskedAccount(),
                'status'         => $w->status->value,
                'status_label'   => $w->status->label(),
                'created'        => $w->created_at->format('d M Y'),
                'url'            => route('admin.withdrawals.show', $w),
            ]),
            'filters' => [
                'q'      => (string) request('q', ''),
                'status' => $status,
            ],
            'statuses' => array_map(
                fn ($s) => ['value' => $s, 'label' => $s === 'all' ? 'All' : ucfirst($s)],
                self::STATUSES,
            ),
        ]);
    }

    public function show(Withdrawal $withdrawal)
    {
        $this->authorize('withdrawals.view');
        // Blade over-loaded reviewer/approver/completer but only renders the
        // requesting user + their wallet, so trim to that.
        $withdrawal->load('user.wallet');
        $wallet = $withdrawal->user?->wallet;

        return Inertia::render('Admin/Withdrawals/Show', [
            'withdrawal' => [
                'id'               => $withdrawal->id,
                'reference'        => $withdrawal->reference(),
                'user_name'        => $withdrawal->user?->name ?? '—',
                'status'           => $withdrawal->status->value,
                'status_label'     => $withdrawal->status->label(),
                'amount_formatted' => Money::format((int) $withdrawal->amount),
                'fee_formatted'    => Money::format((int) $withdrawal->fee),
                'net_formatted'    => Money::format((int) $withdrawal->net_amount),
                'provider'         => $withdrawal->methodLabel(),
                'account'          => $withdrawal->maskedAccount(),
                'requested'        => $withdrawal->created_at->format('d M Y, H:i'),
                'approved_at'      => $withdrawal->approved_at?->format('d M Y, H:i'),
                'rejected_at'      => $withdrawal->rejected_at?->format('d M Y, H:i'),
                'cancelled_at'     => $withdrawal->cancelled_at?->format('d M Y, H:i'),
                'completed_at'     => $withdrawal->processed_at?->format('d M Y, H:i'),
                'external_reference' => $withdrawal->external_reference,
                'rejection_reason' => $withdrawal->rejection_reason,
            ],
            'wallet' => $wallet ? [
                'available_formatted' => Money::format((int) $wallet->available_balance),
                'pending_formatted'   => Money::format((int) $wallet->pending_balance),
            ] : null,
        ]);
    }

    public function approve(Withdrawal $withdrawal)
    {
        $this->authorize('withdrawals.approve');
        $this->service->approve($withdrawal, Auth::user());
        return back()->with('success', 'Withdrawal approved. Mark as completed once MFS transfer is done.');
    }

    public function reject(Request $request, Withdrawal $withdrawal)
    {
        // Rejecting returns money to a user, so it uses its own permission rather
        // than borrowing the approve one.
        $this->authorize('withdrawals.reject');
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $this->service->reject($withdrawal, Auth::user(), $data['reason']);
        return back()->with('success', 'Withdrawal rejected. Funds returned to user wallet.');
    }

    public function complete(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('withdrawals.complete');
        $data = $request->validate(['external_reference' => 'nullable|string|max:200']);
        $this->service->complete($withdrawal, Auth::user(), $data['external_reference'] ?? '');
        return back()->with('success', 'Withdrawal marked as completed.');
    }
}
