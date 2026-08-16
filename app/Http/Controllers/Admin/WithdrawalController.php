<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\AuditLogger;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $service,
        private readonly AuditLogger       $audit,
    ) {}

    public function index()
    {
        $status = request('status','pending');
        $withdrawals = Withdrawal::query()
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when(request('q'), fn($q) => $q->whereHas('user', fn($u) => $u->where('name','like','%'.request('q').'%')
                ->orWhere('email','like','%'.request('q').'%')))
            ->with('user','approver','completer')
            ->latest()->paginate(20);
        return view('admin.withdrawals', compact('withdrawals','status'));
    }

    public function show(Withdrawal $withdrawal)
    {
        $this->authorize('withdrawals.view');
        $withdrawal->load('user.wallet','reviewer','approver','completer');
        return view('admin.withdrawals-show', compact('withdrawal'));
    }

    public function approve(Withdrawal $withdrawal)
    {
        $this->authorize('withdrawals.approve');
        $this->service->approve($withdrawal, Auth::user());
        return back()->with('success', 'Withdrawal approved. Mark as completed once MFS transfer is done.');
    }

    public function reject(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('withdrawals.approve');
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $this->service->reject($withdrawal, Auth::user(), $data['reason']);
        return back()->with('success', 'Withdrawal rejected. Funds returned to user wallet.');
    }

    public function complete(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('withdrawals.approve');
        $data = $request->validate(['external_reference' => 'nullable|string|max:200']);
        $this->service->complete($withdrawal, Auth::user(), $data['external_reference'] ?? '');
        return back()->with('success', 'Withdrawal marked as completed.');
    }
}
